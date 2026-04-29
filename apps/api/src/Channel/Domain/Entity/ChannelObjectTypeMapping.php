<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-channel × per-ObjectType × per-Attribute target field mapping.
 *
 * Replaces the pre-ADR-009 `ChannelAttributeMapping` — by adding
 * `object_type_id` we get one row per (Channel, ObjectType, Attribute)
 * triple, so a single Channel can publish products with different field
 * shapes than categories or assets. Example:
 *
 *   Channel "shopify_pl"
 *     × ObjectType=product × Attribute=color → "metafield.custom.color"
 *     × ObjectType=product × Attribute=name  → "title"
 *     × ObjectType=category × Attribute=name → "title"
 *
 * `target_field` is a free-form string — every integration adapter
 * (Shopify, BaseLinker, …) interprets it; the schema does not enforce
 * any shape. `is_published` lets admins disable a mapping without
 * deleting it (audit trail, A/B configurations).
 *
 * Tenant scope is inherited via the parent Channel — no own `tenant_id`.
 * The table joins `INFRA_TABLES` allowlist in `pim:tenant:audit`.
 */
class ChannelObjectTypeMapping
{
    private Uuid $id;

    private Channel $channel;

    private ObjectType $objectType;

    private Attribute $attribute;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $targetField;

    private bool $isPublished = true;

    public function __construct(
        Channel $channel,
        ObjectType $objectType,
        Attribute $attribute,
        string $targetField,
        bool $isPublished = true,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->channel = $channel;
        $this->objectType = $objectType;
        $this->attribute = $attribute;
        $this->targetField = $targetField;
        $this->isPublished = $isPublished;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getObjectType(): ObjectType
    {
        return $this->objectType;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getTargetField(): string
    {
        return $this->targetField;
    }

    public function setTargetField(string $targetField): void
    {
        $this->targetField = $targetField;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setPublished(bool $published): void
    {
        $this->isPublished = $published;
    }
}
