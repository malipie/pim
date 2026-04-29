<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Provenance;
use App\Catalog\Infrastructure\Doctrine\Repository\ObjectValueRepository;
use App\Identity\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * One value for one (object, attribute) pair.
 *
 * Hybrid attribute model per ADR-006: the canonical store is `object_values`
 * (this table) with a JSONB `value` column; the denormalised cache is
 * `objects.attributes_indexed` (kept in sync by Doctrine listeners in
 * #38). Reads that need cross-attribute queries hit the cache via GIN;
 * writes go through this table and trigger an attributes_indexed rebuild.
 *
 * `scope` (channel_id, nullable — global) and `locale` (nullable — no
 * locale) carry the per-channel / per-locale variants when an Attribute
 * is `is_scopable` or `is_localizable`. UNIQUE on (object_id,
 * attribute_id, channel_id, locale) — partial uniqueness with NULLs is
 * native to Postgres (NULLs are distinct), so we put a deferred
 * uniqueness check via UNIQUE NULLS NOT DISTINCT (Postgres 15+).
 *
 * `provenance` + `provenance_meta` answer "who set this?" — admin UI
 * (#61) reads them for the per-field badge ("manual" / "import" /
 * "integration"). Phase 2 adds the `agent` case to {@see Provenance}.
 */
#[ORM\Entity(repositoryClass: ObjectValueRepository::class)]
#[ORM\Table(name: 'object_values')]
#[ORM\UniqueConstraint(
    name: 'object_values_scope_uniq',
    columns: ['object_id', 'attribute_id', 'channel_id', 'locale'],
    options: ['nulls_not_distinct' => true],
)]
#[ORM\Index(name: 'object_values_tenant_idx', columns: ['tenant_id'])]
#[ORM\Index(name: 'object_values_object_idx', columns: ['object_id'])]
#[ORM\Index(name: 'object_values_attribute_idx', columns: ['attribute_id'])]
class ObjectValue implements TenantScoped
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(targetEntity: CatalogObject::class)]
    #[ORM\JoinColumn(name: 'object_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CatalogObject $object;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Attribute $attribute;

    #[ORM\Column(name: 'channel_id', type: 'uuid', nullable: true)]
    private ?Uuid $channelId = null;

    #[ORM\Column(type: 'string', length: 8, nullable: true)]
    private ?string $locale = null;

    /**
     * Polymorphic JSONB payload — shape depends on Attribute.type:
     *   - text/number/date/boolean: scalar wrapped (`{value: ...}`)
     *   - select: `{option_code: 'red'}`
     *   - multiselect: `{option_codes: ['red', 'blue']}`
     *   - asset: `{asset_id: '...'}`
     *   - relation: `{object_id: '...'}`
     *   - price: `{amount: 19.99, currency: 'PLN'}`
     *   - metric: `{value: 12.5, unit: 'kg'}`
     *
     * Per-type validation lives in #39.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $value;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: Provenance::class, options: ['default' => Provenance::Manual->value])]
    private Provenance $provenance = Provenance::Manual;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'provenance_meta', type: Types::JSON, options: ['jsonb' => true, 'default' => '{}'])]
    private array $provenanceMeta = [];

    /**
     * @param array<string, mixed> $value
     */
    public function __construct(
        CatalogObject $object,
        Attribute $attribute,
        array $value,
        Provenance $provenance = Provenance::Manual,
        ?Uuid $channelId = null,
        ?string $locale = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->object = $object;
        $this->attribute = $attribute;
        $this->value = $value;
        $this->provenance = $provenance;
        $this->channelId = $channelId;
        $this->locale = $locale;
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

    public function getObject(): CatalogObject
    {
        return $this->object;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
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

    /**
     * @return array<string, mixed>
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function setValue(array $value): void
    {
        $this->value = $value;
    }

    public function getProvenance(): Provenance
    {
        return $this->provenance;
    }

    public function setProvenance(Provenance $provenance): void
    {
        $this->provenance = $provenance;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProvenanceMeta(): array
    {
        return $this->provenanceMeta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function setProvenanceMeta(array $meta): void
    {
        $this->provenanceMeta = $meta;
    }
}
