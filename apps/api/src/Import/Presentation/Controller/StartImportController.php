<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Import\Application\Handler\ImportRunHandler;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportImageSource;
use App\Import\Domain\Enum\ImportMode;
use App\Import\Domain\Message\ImportRunMessage;
use App\Import\Domain\Repository\ImportProfileRepositoryInterface;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\BulkOperationInProgressException;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Messenger\Stamp\TenantStamp;
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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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

    /** IMP2-1.13 — server-side ZIP upload cap (D13). */
    private const int MAX_ZIP_BYTES = 500 * 1024 * 1024;

    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly AttributeRepositoryInterface $attributes,
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
    #[RequiresPermission(module: 'imports', action: 'run')]
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

        // IMP2-1.6a (#1468, ADR-0019 D8): the sync/async split counts DATA
        // ROWS, not bytes — a wide 20-row CSV stays inline, a narrow 500-row
        // one goes to the worker. Counting stops at the threshold; the +1
        // header line is excluded.
        $dataRowCount = str_ends_with(strtolower($originalName), '.csv')
            ? $this->countDataRows($file->getPathname(), self::SYNC_THRESHOLD_ROWS + 1)
            // XLSX is a zip — line counting is meaningless; spreadsheets
            // always take the worker path (cheap now that dev runs one).
            : self::SYNC_THRESHOLD_ROWS + 1;
        // IMP2-1.13 — optional ZIP of images. Validate extension + 500 MB cap
        // server-side (FE pre-validates too); the bytes are streamed to MinIO
        // after the session is persisted.
        $zipFile = $request->files->get('zip_file');
        $zipName = null;
        $zipSize = null;
        if ($zipFile instanceof UploadedFile) {
            $zipName = $zipFile->getClientOriginalName();
            if ('' === $zipName || !str_ends_with(strtolower($zipName), '.zip')) {
                throw new BadRequestHttpException('"zip_file" must be a .zip archive.');
            }
            $zipSize = (int) $zipFile->getSize();
            if ($zipSize > self::MAX_ZIP_BYTES) {
                throw new UnprocessableEntityHttpException(\sprintf('ZIP exceeds the %d MB limit.', self::MAX_ZIP_BYTES >> 20));
            }
        }

        $session = new ImportSession(
            userId: $user->getId(),
            targetObjectType: $objectType,
            fileName: $originalName,
            fileSizeBytes: (int) $file->getSize(),
            profile: $profile,
            zipFileName: $zipName,
            zipFileSizeBytes: $zipSize,
        );

        $mode = $this->resolveMode($request->request->get('mode'), $profile);
        $matchAttributeCode = $this->resolveMatchAttributeCode(
            $request->request->get('match_attribute_code'),
            $profile,
            $tenant,
        );
        $session->configureRun($mode, $matchAttributeCode);
        $session->setColumnMapping($mapping);
        // IMP2-1.13 — a ZIP forces zip mode; otherwise honour the wizard's
        // image_source (http|none). http behaves like none for the engine
        // (URL cells are auto-detected), so only `zip` changes routing.
        $session->setImageSource(
            null !== $zipName
                ? ImportImageSource::Zip
                : (ImportImageSource::tryFrom((string) $request->request->get('image_source', 'none')) ?? ImportImageSource::None),
        );

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

        // IMP2-1.13 — stage the ZIP next to the data file (same tenant/session
        // prefix); the media handler downloads + extracts it after the row phase.
        if ($zipFile instanceof UploadedFile) {
            $zipRemotePath = \sprintf('%s/%s/%s', $tenant->getId()->toRfc4122(), $session->getId()->toRfc4122(), $zipName);
            try {
                $zipStream = fopen($zipFile->getPathname(), 'r');
                if (false === $zipStream) {
                    throw new RuntimeException('Failed to open uploaded ZIP for reading.');
                }
                $this->importsStorage->writeStream($zipRemotePath, $zipStream);
                if (\is_resource($zipStream)) {
                    fclose($zipStream);
                }
            } catch (FilesystemException|RuntimeException $exception) {
                $session->markFailed(\sprintf('Failed to stage uploaded ZIP: %s', $exception->getMessage()));
                $this->sessions->save($session);
                throw new BadRequestHttpException('Failed to stage the uploaded ZIP.', $exception);
            }
        }

        // Spec decision §3: <50 rows runs inline so the operator does not
        // wait on a worker; `total_rows` is unknown until the handler
        // streams the file, so we use the request-time threshold based on
        // `file_size_bytes` heuristic (small files always sync).
        if ($dataRowCount <= self::SYNC_THRESHOLD_ROWS) {
            $reload = $this->sessions->findById($session->getId());
            if ($reload instanceof ImportSession) {
                try {
                    $this->runHandler->run($reload);
                } catch (BulkOperationInProgressException $exception) {
                    // PROD-05 — translate the domain collision into a 409
                    // so the operator sees a clear conflict response. The
                    // ImportSession row stays `pending`; user can retry
                    // once the in-flight bulk job releases the lock.
                    throw new ConflictHttpException($exception->getMessage(), $exception);
                }
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
        ), [new TenantStamp($tenant->getId())]);

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
            'updated_count' => $session->getUpdatedCount(),
            'skipped_count' => $session->getSkippedCount(),
            'mode' => $session->getMode()->value,
            'started_at' => $session->getStartedAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'completed_at' => $session->getCompletedAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'rollback_until' => $session->getRollbackUntil()?->format(DateTimeInterface::RFC3339_EXTENDED),
        ];
    }

    private function resolveMode(mixed $raw, ?ImportProfile $profile): ImportMode
    {
        if (\is_string($raw) && '' !== $raw) {
            $mode = ImportMode::tryFrom(strtoupper($raw));
            if (!$mode instanceof ImportMode) {
                throw new BadRequestHttpException(\sprintf('Invalid "mode" "%s" — expected CREATE, UPDATE or UPSERT.', $raw));
            }

            return $mode;
        }

        return $profile?->getMode() ?? ImportMode::Upsert;
    }

    private function resolveMatchAttributeCode(mixed $raw, ?ImportProfile $profile, Tenant $tenant): ?string
    {
        $code = \is_string($raw) && '' !== trim($raw) ? trim($raw) : $profile?->getMatchAttributeCode();
        if (null === $code || '' === $code) {
            return null;
        }

        $attribute = $this->attributes->findByCode($code, $tenant);
        if (!$attribute instanceof Attribute) {
            throw new BadRequestHttpException(\sprintf('Unknown "match_attribute_code" "%s".', $code));
        }
        if (AttributeType::Identifier !== $attribute->getType()) {
            throw new BadRequestHttpException(\sprintf('"match_attribute_code" "%s" must be an identifier attribute, got "%s".', $code, $attribute->getType()->value));
        }

        return $code;
    }

    /**
     * Counts non-empty data lines (header excluded), stopping at $cap —
     * enough to answer "inline or worker?" without reading a huge file.
     */
    private function countDataRows(string $path, int $cap): int
    {
        $handle = @fopen($path, 'r');
        if (false === $handle) {
            return $cap;
        }
        $count = -1; // first line is the header
        try {
            while (false !== ($line = fgets($handle))) {
                if ('' === trim($line)) {
                    continue;
                }
                ++$count;
                if ($count >= $cap) {
                    return $count;
                }
            }
        } finally {
            fclose($handle);
        }

        return max(0, $count);
    }
}
