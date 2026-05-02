<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Exception\CannotDisablePrimaryLocaleException;
use App\Shared\Domain\Exception\InvalidLocaleException;
use App\Shared\Domain\Exception\LocaleNotEnabledException;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant aggregate — the single shared kernel of multi-tenant PIM.
 *
 * Lives in Shared/ because every business bounded context (Catalog, Channel,
 * Asset, Integration) carries a `tenant_id` foreign key on its aggregates.
 * Identity owns User/Role/Permission/RefreshToken but no longer owns Tenant
 * itself — those belong to the universal isolation boundary.
 *
 * Mapping kept in XML at Shared/Infrastructure/Doctrine/Orm/Mapping/Tenant.orm.xml
 * so the Domain class stays framework-agnostic.
 */
class Tenant
{
    public const string PLAN_STARTER = 'starter';
    public const string PLAN_PRO = 'pro';
    public const string PLAN_ENTERPRISE = 'enterprise';

    private Uuid $id;
    private string $code;
    private string $name;
    private ?string $domain;
    private string $plan;
    private DateTimeImmutable $createdAt;

    /**
     * Per-workspace list of enabled locales — drives the LocaleTabsField in
     * the modeling UI (VIEW-01) and any future multilingual surface. Default
     * `['pl', 'en']` matches the seeded demo workspace and the schema column
     * default. The CHECK constraint on `tenants` guarantees `primaryLocale`
     * is always in this list.
     *
     * @var list<string>
     */
    private array $enabledLocales = ['pl', 'en'];

    private string $primaryLocale = 'pl';

    public function __construct(
        string $code,
        string $name,
        ?Uuid $id = null,
        ?string $domain = null,
        string $plan = self::PLAN_STARTER,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->domain = $domain;
        $this->plan = $plan;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function changeDomain(?string $domain): void
    {
        $this->domain = $domain;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function changePlan(string $plan): void
    {
        $this->plan = $plan;
    }

    /**
     * @return list<string>
     */
    public function getEnabledLocales(): array
    {
        return $this->enabledLocales;
    }

    public function getPrimaryLocale(): string
    {
        return $this->primaryLocale;
    }

    public function isLocaleEnabled(string $locale): bool
    {
        return \in_array($locale, $this->enabledLocales, true);
    }

    /**
     * Adds a locale to the enabled set. Idempotent — a duplicate add is a
     * no-op so the FE LocaleAddDialog can fire-and-forget without checking
     * server state first. Validates against `LocaleLibrary::CODES` to keep
     * the column from collecting typos.
     */
    public function enableLocale(string $locale): void
    {
        if (!LocaleLibrary::isSupported($locale)) {
            throw new InvalidLocaleException($locale);
        }
        if ($this->isLocaleEnabled($locale)) {
            return;
        }
        $this->enabledLocales[] = $locale;
    }

    /**
     * Removes a locale from the enabled set. Refuses to remove the primary —
     * the operator must change the primary first. Idempotent on already-disabled.
     */
    public function disableLocale(string $locale): void
    {
        if ($locale === $this->primaryLocale) {
            throw new CannotDisablePrimaryLocaleException($locale);
        }
        $this->enabledLocales = array_values(array_filter(
            $this->enabledLocales,
            static fn (string $l): bool => $l !== $locale,
        ));
    }

    public function changePrimaryLocale(string $locale): void
    {
        if (!$this->isLocaleEnabled($locale)) {
            throw new LocaleNotEnabledException($locale);
        }
        $this->primaryLocale = $locale;
    }
}
