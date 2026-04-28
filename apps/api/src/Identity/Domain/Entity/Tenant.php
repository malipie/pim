<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Identity\Infrastructure\Doctrine\Repository\TenantRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TenantRepository::class)]
#[ORM\Table(name: 'tenants')]
#[ORM\UniqueConstraint(name: 'tenants_code_uniq', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'tenants_domain_uniq', columns: ['domain'])]
class Tenant
{
    public const string PLAN_STARTER = 'starter';
    public const string PLAN_PRO = 'pro';
    public const string PLAN_ENTERPRISE = 'enterprise';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $domain;

    #[ORM\Column(type: 'string', length: 32, options: ['default' => self::PLAN_STARTER])]
    private string $plan;

    #[ORM\Column(type: 'datetime_immutable')]
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

    public function setDomain(?string $domain): void
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
