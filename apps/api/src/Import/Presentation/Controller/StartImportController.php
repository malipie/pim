<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Import\Application\Handler\ImportRunHandler;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Message\ImportRunMessage;
use App\Import\Domain\Repository\ImportProfileRepositoryInterface;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use DateTimeInterface;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * IMP-04 (#445) wizard Step 4 confirm — uploads the source file to the
 * imports bucket, persists an {@see ImportSession}, and either runs
 * the import inline (rows < 50, spec §3 sync threshold) or hands it
 * off to the async worker via {@see ImportRunMessage}.
 *
 * Multipart fields:
 *   - `file` — CSV / xlsx
 *   - `target_object_type_id` — UUID
 *   - `mapping` — JSON `{column_header: attribute_code | "skip"}`
 *   - `profile_id` — UUID, optional
 *   - `locale`, `encoding`, `delimiter` — optional overrides
 *   - `do_backup` — boolean, optional (forwarded to IMP-06 in a follow-up)
 */
final class StartImportController
{
    private const int SYNC_THRESHOLD_ROWS = 50;

    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly ImportProfileRepositoryInterface $profiles,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly ImportRunHandler $runHandler,
        private readonly MessageBusInterface $bus,
        private readonly Security $security,
        private readonly TenantContext $tenantContext,
        private readonly FilesystemOperator $importsStorage,
    ) {
    }

    #[Route(
        path: '/api/import-sessions',
        name: 'imports_start',
        methods: ['POST'],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }
        $tenant = $user->getTenant();
        $this->tenantContext->set($tenant);

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('"file" multipart field is required.');
        }

        $targetId = $this->parseUuid($request->request->get('target_object_type_id'), 'target_object_type_id');
        $objectType = $this->objectTypes->findById($targetId);
        if (!$objectType instanceof ObjectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $targetId->toRfc4122()));
        }

        $mapping = $this->parseMapping($request->request->get('mapping', '{}'));
        $profile = $this->resolveProfile($request->request->get('profile_id'), $tenant, $user->getId(), $mapping, $objectType);

        $originalName = $file->getClientOriginalName();
        if ('' === $originalName) {
            $originalName = 'upload.csv';
        }
        $session = new ImportSession(
            userId: $user->getId(),
            targetObjectType: $objectType,
            fileName: $originalName,
            fileSizeBytes: (int) $file->getSize(),
            profile: $profile,
        );
        $session->setColumnMapping($mapping);

        // Persist before upload so a later upload failure has a session id
        // to attach to (status stays `pending`, error_message captures
        // the reason). The TenantAssignmentListener stamps tenant_id on
        // pre-persist.
        $this->sessions->save($session);

        $remotePath = \sprintf(
            '%s/%s/%s',
            $tenant->getId()->toRfc4122(),
            $session->getId()->toRfc4122(),
            $session->getFileName(),
        );
        try {
            $stream = fopen($file->getPathname(), 'r');
            if (false === $stream) {
                throw new RuntimeException('Failed to open uploaded file for reading.');
            }
            $this->importsStorage->writeStream($remotePath, $stream);
            if (\is_resource($stream)) {
                fclose($stream);
            }
        } catch (FilesystemException|RuntimeException $exception) {
            $session->markFailed(\sprintf('Failed to stage uploaded file: %s', $exception->getMessage()));
            $this->sessions->save($session);
            throw new BadRequestHttpException('Failed to stage the uploaded file.', $exception);
        }

        // Spec decision §3: <50 rows runs inline so the operator does not
        // wait on a worker; `total_rows` is unknown until the handler
        // streams the file, so we use the request-time threshold based on
        // `file_size_bytes` heuristic (small files always sync).
        if ($session->getFileSizeBytes() <= self::SYNC_THRESHOLD_ROWS * 1024) {
            $reload = $this->sessions->findById($session->getId());
            if ($reload instanceof ImportSession) {
                $this->runHandler->run($reload);
                $reload = $this->sessions->findById($session->getId()) ?? $reload;

                return new JsonResponse(
                    $this->serialise($reload),
                    Response::HTTP_OK,
                );
            }
        }

        $this->bus->dispatch(new ImportRunMessage(
            importSessionId: $session->getId(),
            tenantId: $tenant->getId(),
        ));

        return new JsonResponse($this->serialise($session), Response::HTTP_ACCEPTED);
    }

    private function parseUuid(mixed $raw, string $field): Uuid
    {
        if (!\is_string($raw) || '' === $raw) {
            throw new BadRequestHttpException(\sprintf('"%s" is required.', $field));
        }
        try {
            return Uuid::fromString($raw);
        } catch (InvalidArgumentException) {
            throw new BadRequestHttpException(\sprintf('Invalid "%s" UUID "%s".', $field, $raw));
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseMapping(mixed $raw): array
    {
        if (!\is_string($raw)) {
            throw new BadRequestHttpException('"mapping" must be a JSON string.');
        }
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('"mapping" must be a JSON object.');
        }

        $mapping = [];
        foreach ($decoded as $key => $value) {
            $mapping[(string) $key] = \is_string($value) ? $value : 'skip';
        }

        return $mapping;
    }

    /**
     * @param array<string, string> $mapping
     */
    private function resolveProfile(mixed $raw, Tenant $tenant, Uuid $userId, array $mapping, ObjectType $objectType): ?ImportProfile
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }
        try {
            $profileId = Uuid::fromString($raw);
        } catch (InvalidArgumentException) {
            throw new BadRequestHttpException(\sprintf('Invalid "profile_id" UUID "%s".', $raw));
        }

        $profile = $this->profiles->findById($profileId);
        if (!$profile instanceof ImportProfile) {
            throw new NotFoundHttpException(\sprintf('ImportProfile "%s" was not found.', $raw));
        }
        if ($profile->getUserId()->toRfc4122() !== $userId->toRfc4122()) {
            throw new NotFoundHttpException(\sprintf('ImportProfile "%s" was not found.', $raw));
        }
        $profile->touchLastUsed();
        $this->profiles->save($profile);

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialise(ImportSession $session): array
    {
        return [
            'id' => $session->getId()->toRfc4122(),
            'status' => $session->getStatus()->value,
            'total_rows' => $session->getTotalRows(),
            'success_count' => $session->getSuccessCount(),
            'error_count' => $session->getErrorCount(),
            'started_at' => $session->getStartedAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'completed_at' => $session->getCompletedAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'rollback_until' => $session->getRollbackUntil()?->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }
}
