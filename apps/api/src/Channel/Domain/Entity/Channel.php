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
 *   - a name (plain string) — what admins see in the UI. Internal-only,
 *     never published to a destination, so it is intentionally single-language;
 *   - a set of supported `Locale` rows (M2M `channel_locales`);
 *   - an optional `category_tree_root_object_id` pointing at a
 *     `CatalogObject` of `kind=category` — the root of the category tree
 *     this channel publishes. Validation ("the target must be a
 *     category") lives in
 *     {@see \App\Channel\Infrastructure\Doctrine\EventListener\ChannelCategoryRootValidator}.
 *
 * Per-channel attribute→target-field mapping is not modelled here yet — it
 * belongs to the API integration configuration (Faza 1) and will be added
 * with the first real export integration.
 */
class Channel extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $code;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    /**
     * @var Collection<int, Locale>
     */
    private Collection $locales;

    /**
     * Stored as a bare UUID rather than a Doctrine relation to keep Channel
     * Domain free of cross-BC entity imports — the FK lives at the DB layer
     * (CASCADE-on-delete in the migration), the kind=category invariant is
     * enforced by ChannelCategoryRootValidator via GetObjectSummary (RF-19).
     */
    private ?Uuid $categoryTreeRootId = null;

    public function __construct(string $code, string $name, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->locales = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
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
