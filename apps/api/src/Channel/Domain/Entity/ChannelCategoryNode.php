<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * A navigation-tree node for a sales/publication {@see Channel} (CHC-01).
 *
 * Channel category trees (Allegro, Shopify, BaseLinker, ...) are a different
 * thing from master categories: they carry an `external_code` (the id of the
 * category in the destination system), belong to a single channel, and never
 * drive the product form. They therefore live in their own table — the master
 * {@see \App\Catalog\Domain\Entity\CatalogObject} tree and the
 * EffectiveAttributeGroupResolver never see this entity.
 *
 * The `path` LTREE column encodes ancestry for fast descendant queries; its
 * labels are derived from each node's UUID (hex, dashes stripped) so they are
 * always valid ltree labels regardless of the operator-provided `code`.
 */
class ChannelCategoryNode implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Channel $channel;

    private ?ChannelCategoryNode $parent;

    private string $code;

    /**
     * @var array<string, string>
     */
    private array $label;

    /**
     * Postgres LTREE path (`{@see \App\Catalog\Infrastructure\Doctrine\Type\LtreeType}`
     * maps it as `?string`). Built from the parent path + this node's uuid label.
     */
    private ?string $path = null;

    private ?string $externalCode;

    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $label
     */
    public function __construct(
        Channel $channel,
        string $code,
        array $label,
        ?self $parent = null,
        ?string $externalCode = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->channel = $channel;
        $this->code = $code;
        $this->label = $label;
        $this->parent = $parent;
        $this->externalCode = self::normalizeExternalCode($externalCode);
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * The ltree label for this node — its UUID hex with dashes stripped. Always
     * a valid single ltree label (alphanumeric), independent of `$code`.
     */
    public function ltreeLabel(): string
    {
        return str_replace('-', '', $this->id->toRfc4122());
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

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getChannelId(): Uuid
    {
        return $this->channel->getId();
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getParentId(): ?Uuid
    {
        return $this->parent?->getId();
    }

    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @param array<string, string> $label
     */
    public function rename(array $label): void
    {
        $this->label = $label;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function attachToPath(string $path): void
    {
        $this->path = $path;
    }

    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }

    public function changeExternalCode(?string $externalCode): void
    {
        $this->externalCode = self::normalizeExternalCode($externalCode);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function normalizeExternalCode(?string $externalCode): ?string
    {
        if (null === $externalCode) {
            return null;
        }

        $trimmed = trim($externalCode);

        return '' === $trimmed ? null : $trimmed;
    }
}
