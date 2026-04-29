<?php

declare(strict_types=1);

namespace App\Shared\Domain;

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
}
