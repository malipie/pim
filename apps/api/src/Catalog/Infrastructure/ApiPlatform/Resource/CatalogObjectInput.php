<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input shape for the per-kind sugar paths
 * (`/api/products`, `/api/categories`, `/api/assets`).
 *
 * The DTO carries only the fields the create command needs — `code` and
 * `objectTypeId`. `kind` is read by the processor from the operation's
 * `extraProperties.kind` rather than the body, so a payload cannot
 * smuggle in a different kind than the path implies.
 *
 * Setter-less Domain entities mean the AP4 default Doctrine processor
 * cannot flush a hydrated `CatalogObject`. Instead, this DTO is what AP4
 * deserializes; the processor reads it, builds a Command, and dispatches.
 */
final class CatalogObjectInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    #[Groups(['object:create'])]
    public string $code = '';

    /**
     * UUID of the {@see \App\Catalog\Domain\Entity\ObjectType} the new
     * row belongs to. The processor enforces that
     * `objectType.kind === extraProperties.kind` (per sugar path).
     */
    #[Assert\NotBlank]
    #[Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])]
    #[Groups(['object:create'])]
    public string $objectTypeId = '';

    /**
     * Optional self-reference: variants for `kind=product`, tree edges for
     * `kind=category`. UUID format only — kind compatibility is checked
     * by the constructor + Doctrine listener after persist.
     */
    #[Assert\Uuid(versions: [Assert\Uuid::V7_MONOTONIC])]
    #[Groups(['object:create'])]
    public ?string $parentId = null;

    /**
     * Per-attribute values keyed by attribute code:
     * `{"color": "red", "weight": 12.5, "name": {"pl": "Test"}}`.
     *
     * The processor resolves each code to an `Attribute` (per-tenant)
     * and creates an `ObjectValue` with `provenance=Manual`. Unknown
     * codes are dropped silently — strict-mode validation lands when
     * the admin UI gains its dynamic schema picker.
     *
     * #45: ObjectDenormalizer/Normalizer parametryzowany per
     * `object_type_id`. The flat dict here is the canonical write
     * shape; the read shape lives in `CatalogObject.attributesIndexed`
     * (denormalised cache rebuilt by `AttributesIndexedSyncListener`
     * after each ObjectValue change).
     *
     * @var array<string, mixed>|null
     */
    #[Groups(['object:create'])]
    public ?array $attributes = null;
}
