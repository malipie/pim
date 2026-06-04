<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Per (tenant, channel, objectType) publication profile.
 *
 * Controls which attributes and locales are exported / surfaced when a
 * consumer asks for `?publication=<channelCode>`. ADR-0018.
 *
 * - `publishedAttributeCodes = null` means publish-all (the default).
 * - `publishedAttributeCodes = []` means publish-nothing.
 * - `publishedLocales` filters per-locale values; defaults to all
 *   tenant locales when empty (see resolver).
 * - `isDefault = true` marks the auto-created publish-all profile that
 *   is provisioned by {@see CreateDefaultPublicationProfilesOnChannelCreated}
 *   on `ChannelCreated`; exactly one default per (tenant, channel, objectType).
 *
 * Cross-BC refs use bare UUID columns per ADR-015 — no Doctrine associations.
 */
class ChannelPublicationProfile implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $channelId;

    private Uuid $objectTypeId;

    /**
     * null = publish-all; [] = publish-nothing; non-empty = allow-list.
     *
     * @var list<string>|null
     */
    private ?array $publishedAttributeCodes;

    /**
     * Short locale codes (e.g. 'pl', 'en'). Empty = use tenant locales.
     *
     * @var list<string>
     */
    private array $publishedLocales;

    /**
     * Optional per-attribute column header aliases for export.
     *
     * @var array<string, string>
     */
    private array $columnAliases;

    private bool $isDefault;

    private DateTimeImmutable $createdAt;

    /**
     * @param list<string>|null    $publishedAttributeCodes
     * @param list<string>         $publishedLocales
     * @param array<string,string> $columnAliases
     */
    public function __construct(
        Uuid $channelId,
        Uuid $objectTypeId,
        ?array $publishedAttributeCodes = null,
        array $publishedLocales = [],
        array $columnAliases = [],
        bool $isDefault = false,
        ?Tenant $tenant = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->channelId = $channelId;
        $this->objectTypeId = $objectTypeId;
        $this->publishedAttributeCodes = $publishedAttributeCodes;
        $this->publishedLocales = $publishedLocales;
        $this->columnAliases = $columnAliases;
        $this->isDefault = $isDefault;
        $this->tenant = $tenant;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @internal stamped by TenantAssignmentListener on prePersist
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }
        $this->tenant = $tenant;
    }

    public function getChannelId(): Uuid
    {
        return $this->channelId;
    }

    public function getObjectTypeId(): Uuid
    {
        return $this->objectTypeId;
    }

    /**
     * @return list<string>|null null means publish-all
     */
    public function getPublishedAttributeCodes(): ?array
    {
        return $this->publishedAttributeCodes;
    }

    public function isPublishAll(): bool
    {
        return null === $this->publishedAttributeCodes;
    }

    /**
     * @return list<string>
     */
    public function getPublishedLocales(): array
    {
        return $this->publishedLocales;
    }

    /**
     * @return array<string, string>
     */
    public function getColumnAliases(): array
    {
        return $this->columnAliases;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param list<string>|null $codes null restores publish-all
     */
    public function setPublishedAttributeCodes(?array $codes): void
    {
        $this->publishedAttributeCodes = $codes;
    }

    /**
     * @param list<string> $locales
     */
    public function setPublishedLocales(array $locales): void
    {
        $this->publishedLocales = $locales;
    }

    /**
     * @param array<string, string> $aliases
     */
    public function setColumnAliases(array $aliases): void
    {
        $this->columnAliases = $aliases;
    }
}
