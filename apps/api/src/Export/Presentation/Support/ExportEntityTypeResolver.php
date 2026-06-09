<?php

declare(strict_types=1);

namespace App\Export\Presentation\Support;

use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportTargetScope;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * EXR-04 (#1380) — parses and validates the `entity_type` / `object_type_id`
 * contract shared by the sync export controller and the profile CRUD.
 *
 * Rules (EXR spec §2 D2, ticket EXR-04):
 *   - `entity_type` is optional; absence defaults to `product` (backward
 *     compatibility with EXP-epic payloads).
 *   - `custom_module` requires an `object_type_id` referencing a custom
 *     ObjectType (`is_built_in=false`). Every other type forbids it.
 *   - `target_scope` / filters are only valid for `product` and
 *     `custom_module`; structural types are forced to `all`.
 *
 * Malformed input → 400; business-rule violations → 422 (RFC 7807, rendered
 * by the kernel exception listener).
 */
final readonly class ExportEntityTypeResolver
{
    public function __construct(
        private ObjectTypeRepositoryInterface $objectTypes,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function resolve(array $payload): ExportEntityTypeSelection
    {
        $entityType = $this->parseEntityType($payload);
        $objectTypeId = $this->parseObjectTypeId($payload);

        if ($entityType->requiresObjectType()) {
            if (null === $objectTypeId) {
                throw new UnprocessableEntityHttpException(
                    'object_type_id is required when entity_type=custom_module.',
                );
            }
            $objectType = $this->objectTypes->findById($objectTypeId);
            if (null === $objectType) {
                throw new UnprocessableEntityHttpException(sprintf(
                    'object_type_id "%s" does not reference a known ObjectType.',
                    $objectTypeId->toRfc4122(),
                ));
            }
            if ($objectType->isBuiltIn()) {
                throw new UnprocessableEntityHttpException(
                    'entity_type=custom_module requires a custom ObjectType (is_built_in=false); '
                    .'export the built-in catalog with entity_type=product.',
                );
            }
        } elseif (null !== $objectTypeId) {
            throw new UnprocessableEntityHttpException(sprintf(
                'object_type_id is not allowed for entity_type=%s.',
                $entityType->value,
            ));
        }

        return new ExportEntityTypeSelection($entityType, $objectTypeId);
    }

    /**
     * Structural entity types export the full configuration set and may only
     * run with `target_scope=all`. Catalog-backed types accept any scope.
     */
    public function assertScopeAllowed(ExportEntityType $entityType, ExportTargetScope $scope): void
    {
        if ($entityType->supportsScopeAndFilter()) {
            return;
        }
        if (ExportTargetScope::All !== $scope) {
            throw new UnprocessableEntityHttpException(sprintf(
                'entity_type=%s exports the full structure — target_scope must be "all", got "%s".',
                $entityType->value,
                $scope->value,
            ));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parseEntityType(array $payload): ExportEntityType
    {
        $value = $payload['entity_type'] ?? ExportEntityType::Product->value;
        if (!\is_string($value)) {
            throw new BadRequestHttpException('entity_type must be a string.');
        }
        $entityType = ExportEntityType::tryFrom($value);
        if (null === $entityType) {
            throw new BadRequestHttpException(sprintf(
                'Unsupported entity_type "%s" — expected one of: %s.',
                $value,
                implode(', ', array_map(static fn (ExportEntityType $t): string => $t->value, ExportEntityType::cases())),
            ));
        }

        return $entityType;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parseObjectTypeId(array $payload): ?Uuid
    {
        $value = $payload['object_type_id'] ?? null;
        if (null === $value) {
            return null;
        }
        if (!\is_string($value) || !Uuid::isValid($value)) {
            throw new BadRequestHttpException('object_type_id must be an RFC 4122 UUID string or null.');
        }

        return Uuid::fromString($value);
    }
}
