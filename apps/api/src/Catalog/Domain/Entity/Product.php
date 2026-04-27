<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Infrastructure\Doctrine\Repository\ProductRepository;
use App\Identity\Domain\Entity\Tenant;
use ArrayObject;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'products_tenant_sku_uniq', columns: ['tenant_id', 'sku'])]
#[ORM\Index(name: 'products_tenant_idx', columns: ['tenant_id'])]
#[ApiResource(
    shortName: 'Product',
    description: 'A SKU-keyed catalog item scoped to the caller\'s tenant.',
    operations: [
        new GetCollection(
            paginationType: 'cursor',
            paginationViaCursor: [
                ['field' => 'id', 'direction' => 'DESC'],
            ],
            paginationItemsPerPage: 30,
            paginationMaximumItemsPerPage: 100,
            // Default order — newest first. UUID v7 carries the timestamp in
            // its leading bytes, so sorting by id DESC is equivalent to
            // sorting by createdAt DESC and gives the admin list the "your
            // last create lands at the top" UX the operator expects. Without
            // this clients that don't pass `order[id]=desc` get an unstable
            // physical-row order from Postgres.
            order: ['id' => 'DESC'],
        ),
        new Get(),
        new Post(
            openapi: new \ApiPlatform\OpenApi\Model\Operation(
                requestBody: new \ApiPlatform\OpenApi\Model\RequestBody(
                    content: new ArrayObject([
                        'application/ld+json' => [
                            'example' => [
                                'sku' => 'WIDGET-001',
                                'name' => 'Stainless steel widget',
                                'description' => '12mm diameter, food-grade.',
                                'brand' => 'Acme',
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Patch(
            denormalizationContext: ['groups' => ['product:patch']],
        ),
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
)]
#[ApiFilter(OrderFilter::class, properties: ['id' => 'DESC'])]
#[ApiFilter(RangeFilter::class, properties: ['id'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[Groups(['product:read'])]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['product:read', 'product:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $sku;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product:read', 'product:write', 'product:patch'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:patch'])]
    #[Assert\Length(max: 4000)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    #[Groups(['product:read', 'product:write', 'product:patch'])]
    #[Assert\Length(max: 128)]
    private ?string $brand = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['product:read'])]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['product:read'])]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $sku, string $name, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->sku = $sku;
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
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
     * Tenant assignment is performed by TenantAssignmentListener on PrePersist —
     * the entity must not be assigned manually outside of that listener so the
     * invariant "every domain row carries the tenant of the actor that created it"
     * cannot be bypassed.
     *
     * @internal
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }

        $this->tenant = $tenant;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): void
    {
        $this->brand = $brand;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
