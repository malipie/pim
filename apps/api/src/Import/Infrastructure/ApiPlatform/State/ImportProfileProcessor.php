<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Enum\ImportImageSource;
use App\Import\Domain\Enum\ImportMode;
use App\Import\Domain\Repository\ImportProfileRepositoryInterface;
use App\Import\Infrastructure\ApiPlatform\Resource\ImportProfileInput;
use App\Import\Infrastructure\ApiPlatform\Resource\ImportProfilePatchInput;
use App\Shared\Application\TenantContext;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ImportProfileInput|ImportProfilePatchInput|ImportProfile, ImportProfile|null>
 */
final readonly class ImportProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private ImportProfileRepositoryInterface $profiles,
        private ObjectTypeRepositoryInterface $objectTypes,
        private Security $security,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ImportProfile
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

        throw new LogicException(\sprintf(
            'ImportProfileProcessor cannot handle operation "%s".',
            $operation::class,
        ));
    }

    private function handlePost(mixed $data): ImportProfile
    {
        if (!$data instanceof ImportProfileInput) {
            throw new LogicException('ImportProfileProcessor expects ImportProfileInput on Post.');
        }

        $user = $this->currentUser();
        $tenant = $user->getTenant();
        $this->tenantContext->set($tenant);

        try {
            $targetTypeId = Uuid::fromString($data->targetObjectTypeId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $data->targetObjectTypeId));
        }
        $type = $this->objectTypes->findById($targetTypeId);
        if (!$type instanceof ObjectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $data->targetObjectTypeId));
        }

        if (null !== $this->profiles->findByName($tenant, $user->getId(), $data->name)) {
            throw new ConflictHttpException(\sprintf(
                'Import profile "%s" already exists for this user.',
                $data->name,
            ));
        }

        $mode = null !== $data->mode ? ImportMode::from($data->mode) : ImportMode::Upsert;
        $code = $data->code ?? ImportProfile::slugify($data->name);

        $profile = new ImportProfile(
            userId: $user->getId(),
            name: $data->name,
            targetObjectType: $type,
            code: $code,
            mode: $mode,
        );
        $profile->setColumnMapping($data->columnMapping);
        $profile->setLocale($data->locale);
        $profile->setEncoding($data->encoding);
        $profile->setDelimiter($data->delimiter);
        $profile->setImageZipNamingConvention($data->imageZipNamingConvention);
        if (null !== $data->imageSource) {
            $source = ImportImageSource::tryFrom($data->imageSource);
            if (null !== $source) {
                $profile->setImageSource($source);
            }
        }
        $this->profiles->save($profile);

        return $profile;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handlePatch(mixed $data, array $uriVariables): ImportProfile
    {
        if (!$data instanceof ImportProfilePatchInput) {
            throw new LogicException('ImportProfileProcessor expects ImportProfilePatchInput on Patch.');
        }

        $profile = $this->loadProfile($uriVariables);

        if (null !== $data->name && $data->name !== $profile->getName()) {
            $existing = $this->profiles->findByName($profile->getTenant() ?? throw new LogicException('tenant'), $profile->getUserId(), $data->name);
            if (null !== $existing && $existing->getId()->toRfc4122() !== $profile->getId()->toRfc4122()) {
                throw new ConflictHttpException(\sprintf(
                    'Import profile "%s" already exists for this user.',
                    $data->name,
                ));
            }
            $profile->rename($data->name);
        }
        if (null !== $data->code) {
            $profile->setCode($data->code);
        }
        if (null !== $data->mode) {
            $profile->setMode(ImportMode::from($data->mode));
        }
        if (null !== $data->columnMapping) {
            $profile->setColumnMapping($data->columnMapping);
        }
        if (null !== $data->locale) {
            $profile->setLocale($data->locale);
        }
        if (null !== $data->encoding) {
            $profile->setEncoding($data->encoding);
        }
        if (null !== $data->delimiter) {
            $profile->setDelimiter($data->delimiter);
        }
        if (null !== $data->imageZipNamingConvention) {
            $profile->setImageZipNamingConvention($data->imageZipNamingConvention);
        }
        if (null !== $data->imageSource) {
            $source = ImportImageSource::tryFrom($data->imageSource);
            if (null !== $source) {
                $profile->setImageSource($source);
            }
        }

        $this->profiles->save($profile);

        return $profile;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function handleDelete(array $uriVariables): null
    {
        $profile = $this->loadProfile($uriVariables);
        $this->profiles->remove($profile);

        return null;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function loadProfile(array $uriVariables): ImportProfile
    {
        $rawId = $uriVariables['id'] ?? null;
        if ($rawId instanceof Uuid) {
            $id = $rawId;
        } elseif (\is_string($rawId) && '' !== $rawId) {
            try {
                $id = Uuid::fromString($rawId);
            } catch (InvalidArgumentException) {
                throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $rawId));
            }
        } else {
            throw new NotFoundHttpException('Import profile not found.');
        }

        $profile = $this->profiles->findById($id);
        if (!$profile instanceof ImportProfile) {
            throw new NotFoundHttpException(\sprintf('Import profile "%s" was not found.', $id->toRfc4122()));
        }

        return $profile;
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
