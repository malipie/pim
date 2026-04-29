<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Channel\Contracts\Event\CategoryTreeRootAttached;
use App\Channel\Contracts\Event\ChannelCreated;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tenant-scoped sales / publication channel.
 *
 * Examples: `ecommerce_pl` (Polish webstore), `wholesale`, `b2b_export`.
 * Each channel carries:
 *
 *   - a label (`{pl, en}`) — what admins see in the UI;
 *   - a set of supported `Locale` rows (M2M `channel_locales`);
 *   - a set of supported `Currency` rows (M2M `channel_currencies`);
 *   - an optional `category_tree_root_object_id` pointing at a
 *     `CatalogObject` of `kind=category` — the root of the category tree
 *     this channel publishes. Validation ("the target must be a
 *     category") lives in
 *     {@see \App\Channel\Infrastructure\Doctrine\EventListener\ChannelCategoryRootValidator}.
 *
 * Per-channel attribute mappings (e.g. PIM `color` ↔ Shopify
 * `metafield.custom.color`) live in {@see ChannelObjectTypeMapping}, scoped
 * per `ObjectType` so a single channel can have different field shapes
 * for product vs. category exports.
 */
class Channel extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $code;

    /**
     * @var array<string, string>
     */
    #[Assert\Type('array')]
    private array $label;

    /**
     * @var Collection<int, Locale>
     */
    private Collection $locales;

    /**
     * @var Collection<int, Currency>
     */
    private Collection $currencies;

    /**
     * Stored as a bare UUID rather than a Doctrine relation to keep Channel
     * Domain free of cross-BC entity imports — the FK lives at the DB layer
     * (CASCADE-on-delete in the migration), the kind=category invariant is
     * enforced by ChannelCategoryRootValidator via GetObjectSummary (RF-19).
     */
    private ?Uuid $categoryTreeRootId = null;

    /**
     * @param array<string, string> $label
     */
    public function __construct(string $code, array $label, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->label = $label;
        $this->locales = new ArrayCollection();
        $this->currencies = new ArrayCollection();
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
        $this->recordThat(new ChannelCreated(
            channelId: $this->id,
            tenantId: $tenant->getId(),
            code: $this->code,
        ));
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

    /**
     * @return Collection<int, Locale>
     */
    public function getLocales(): Collection
    {
        return $this->locales;
    }

    public function addLocale(Locale $locale): void
    {
        if (!$this->locales->contains($locale)) {
            $this->locales->add($locale);
        }
    }

    public function removeLocale(Locale $locale): void
    {
        $this->locales->removeElement($locale);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function getCurrencies(): Collection
    {
        return $this->currencies;
    }

    public function addCurrency(Currency $currency): void
    {
        if (!$this->currencies->contains($currency)) {
            $this->currencies->add($currency);
        }
    }

    public function removeCurrency(Currency $currency): void
    {
        $this->currencies->removeElement($currency);
    }

    public function getCategoryTreeRootId(): ?Uuid
    {
        return $this->categoryTreeRootId;
    }

    public function attachCategoryTreeRoot(?Uuid $rootId): void
    {
        if ($this->categoryTreeRootId?->toRfc4122() === $rootId?->toRfc4122()) {
            return;
        }

        $this->categoryTreeRootId = $rootId;

        if (null !== $this->tenant) {
            $this->recordThat(new CategoryTreeRootAttached(
                channelId: $this->id,
                tenantId: $this->tenant->getId(),
                categoryTreeRootId: $rootId,
            ));
        }
    }
}
