<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use Symfony\Component\Uid\Uuid;

/**
 * Junction connecting an Attribute to an ObjectType.
 *
 * Replaces the pre-ADR-009 `family_attributes` row. Composite PK is the
 * natural `(object_type_id, attribute_id)` pair — one Attribute can be
 * assigned to many ObjectTypes (e.g. `name` on every kind, `seo_title`
 * on `product` + `category`).
 *
 * `required_for_completeness` flag drives `Object.completeness_pct` (logic
 * in #38). `sort_order` drives form rendering order in admin UI.
 *
 * `channel_id` and `locale` are forward-compatibility fields for per-
 * channel / per-locale attribute scope. They are not used in #32 — Channel
 * entity arrives in #36, and the per-locale override semantics rely on
 * `Attribute.is_localizable` flag from #31. Once both ship, these columns
 * become meaningful (one ObjectTypeAttribute row per channel × locale
 * override or null = applies to all). Until then they stay nullable and
 * MUST be left at null by callers.
 *
 * No `tenant_id` column — tenant scope is inherited via the parent
 * ObjectType. Listed in `TenantAuditCommand::INFRA_TABLES` allowlist so
 * the audit doesn't flag it as an unscoped domain table.
 */
class ObjectTypeAttribute
{
    private ObjectType $objectType;
    private Attribute $attribute;

    private bool $requiredForCompleteness = false;

    private int $sortOrder = 0;
    private ?Uuid $channelId = null;
    private ?string $locale = null;

    public function __construct(
        ObjectType $objectType,
        Attribute $attribute,
        bool $requiredForCompleteness = false,
        int $sortOrder = 0,
    ) {
        $this->objectType = $objectType;
        $this->attribute = $attribute;
        $this->requiredForCompleteness = $requiredForCompleteness;
        $this->sortOrder = $sortOrder;
    }

    public function getObjectType(): ObjectType
    {
        return $this->objectType;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function isRequiredForCompleteness(): bool
    {
        return $this->requiredForCompleteness;
    }

    public function setRequiredForCompleteness(bool $required): void
    {
        $this->requiredForCompleteness = $required;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getChannelId(): ?Uuid
    {
        return $this->channelId;
    }

    public function setChannelId(?Uuid $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }
}
