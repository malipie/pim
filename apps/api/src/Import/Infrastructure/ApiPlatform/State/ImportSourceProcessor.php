<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Domain\Entity\User;
use App\Import\Application\Service\HealthCheck\FolderPathGuard;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Enum\ImportSourceType;
use App\Import\Domain\Repository\ImportProfileRepositoryInterface;
use App\Import\Domain\Repository\ImportSourceRepositoryInterface;
use App\Import\Infrastructure\ApiPlatform\Resource\ImportSourceInput;
use App\Import\Infrastructure\ApiPlatform\Resource\ImportSourcePatchInput;
use App\Shared\Application\TenantContext;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ImportSourceInput|ImportSourcePatchInput|ImportSource, ImportSource|null>
 */
final readonly class ImportSourceProcessor implements ProcessorInterface
{
    public function __construct(
        private ImportSourceRepositoryInterface $sources,
        private ImportProfileRepositoryInterface $profiles,
        private Security $security,
        private TenantContext $tenantContext,
        private FolderPathGuard $folderPathGuard,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ImportSource
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables);
        }
        if ($operation instanceof Post) {
            return $this->handlePost($data);
        }
        if ($operation instanceof Patch) {
            return $this->handlePatch($data, $uriVariables);
        }

        throw new LogicException(\sprintf('ImportSourceProcessor cannot handle operation "%s".', $operation::class));
    }

    private function handlePost(mixed $data): ImportSource
    {
        if (!$data instanceof ImportSourceInput) {
            throw new LogicException('ImportSourceProcessor expects ImportSourceInput on Post.');
        }

        $user = $this->currentUser();
        $tenant = $user->getTenant();
        $this->tenantContext->set($tenant);

        if (null !== $this->sources->findByCode($tenant, $data->code)) {
            throw new ConflictHttpException(\sprintf('Import source with code "%s" already exists.', $data->code));
        }

        $type = ImportSourceType::from($data->type);
        $this->assertPathAllowed($type, $data->path);
        $source = new ImportSource(
            userId: $user->getId(),
            name: $data->name,
            code: $data->code,
            type: $type,
        );
        $source->setHost($data->host);
        $source->setPath($data->path);
        $source->setFilePattern($data->filePattern);
        $source->setAuthRef($data->authRef);
        $source->setPollIntervalSec($data->pollIntervalSec);
        $source->setAutotrigger($data->autotrigger);
        if (null !== $data->profileId) {
            $source->setProfile($this->loadProfile($data->profileId, $user));
        }

        $this->sources->save($source);

        return $source;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): ImportSource
    {
        if (!$data instanceof ImportSourcePatchInput) {
            throw new LogicException('ImportSourceProcessor expects ImportSourcePatchInput on Patch.');
        }

        $source = $this->loadSource($uriVariables);

        if (null !== $data->name) {
            $source->rename($data->name);
        }
        if (null !== $data->code && $data->code !== $source->getCode()) {
            $tenant = $source->getTenant() ?? throw new LogicException('source tenant');
            $existing = $this->sources->findByCode($tenant, $data->code);
            if (null !== $existing && $existing->getId()->toRfc4122() !== $source->getId()->toRfc4122()) {
                throw new ConflictHttpException(\sprintf('Import source code "%s" already exists.', $data->code));
            }
            $source->setCode($data->code);
        }
        if (null !== $data->type) {
            $source->setType(ImportSourceType::from($data->type));
        }
        if (null !== $data->host) {
            $source->setHost($data->host);
        }
        if (null !== $data->path) {
            $effectiveType = null !== $data->type ? ImportSourceType::from($data->type) : $source->getType();
            $this->assertPathAllowed($effectiveType, $data->path);
            $source->setPath($data->path);
        }
        if (null !== $data->filePattern) {
            $source->setFilePattern($data->filePattern);
        }
        if (null !== $data->authRef) {
            $source->setAuthRef($data->authRef);
        }
        if (null !== $data->pollIntervalSec) {
            $source->setPollIntervalSec($data->pollIntervalSec);
        }
        if (null !== $data->autotrigger) {
            $source->setAutotrigger($data->autotrigger);
        }
        if (null !== $data->profileId) {
            $source->setProfile($this->loadProfile($data->profileId, $this->currentUser()));
        }

        $this->sources->save($source);

        return $source;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $source = $this->loadSource($uriVariables);
        $this->sources->remove($source);

        return null;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadSource(array $uriVariables): ImportSource
    {
        $rawId = $uriVariables['id'] ?? null;
        if ($rawId instanceof Uuid) {
            $id = $rawId;
        } elseif (\is_string($rawId) && '' !== $rawId) {
            try {
                $id = Uuid::fromString($rawId);
            } catch (InvalidArgumentException) {
                throw new NotFoundHttpException(\sprintf('Import source "%s" was not found.', $rawId));
            }
        } else {
            throw new NotFoundHttpException('Import source not found.');
        }

        $source = $this->sources->findById($id);
        if (!$source instanceof ImportSource) {
            throw new NotFoundHttpException(\sprintf('Import source "%s" was not found.', $id->toRfc4122()));
        }

        return $source;
    }

    private function loadProfile(string $rawId, User $user): ImportProfile
    {
        try {
            $id = Uuid::fromString($rawId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
        }
        $profile = $this->profiles->findById($id);
        if (!$profile instanceof ImportProfile) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
        }
        if ($profile->getUserId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
        }

        return $profile;
    }

    /**
     * IMP2-2.8 (#1484) — a folder source's path must resolve inside the allowed
     * base directory; otherwise saving it would arm directory enumeration via the
     * health-check probe. Single undifferentiated 422.
     */
    private function assertPathAllowed(ImportSourceType $type, ?string $path): void
    {
        if (ImportSourceType::Folder === $type
            && null !== $path && '' !== $path
            && !$this->folderPathGuard->isWithinBase($path)
        ) {
            throw new UnprocessableEntityHttpException('Path is outside the allowed import sources directory.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }

        return $user;
    }
}
