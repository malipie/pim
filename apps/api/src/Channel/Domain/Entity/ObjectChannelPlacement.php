<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Channel\Domain\ChannelPlacementSource;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Where a product lands in a channel's navigation tree (CHC-02).
 *
 * A separate concept from master category assignment (`object_categories`):
 * different semantics, different table. Tells the integration which channel
 * navigation node a product belongs to. One placement per (object, channel).
 *
 * `objectId` is a soft FK to `objects.id` (a Catalog `CatalogObject`) — kept
 * as a bare Uuid so the Channel context stays free of cross-BC entity imports
 * (deptrac); the schema-level FK still keeps orphans out.
 */
class ObjectChannelPlacement implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $objectId;

    private Channel $channel;

    private ChannelCategoryNode $node;

    private ChannelPlacementSource $source;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $objectId,
        Channel $channel,
        ChannelCategoryNode $node,
        ChannelPlacementSource $source = ChannelPlacementSource::Manual,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->objectId = $objectId;
        $this->channel = $channel;
        $this->node = $node;
        $this->source = $source;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
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

    public function getObjectId(): Uuid
    {
        return $this->objectId;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getChannelId(): Uuid
    {
        return $this->channel->getId();
    }

    public function getNode(): ChannelCategoryNode
    {
        return $this->node;
    }

    public function getNodeId(): Uuid
    {
        return $this->node->getId();
    }

    public function getSource(): ChannelPlacementSource
    {
        return $this->source;
    }

    public function reassign(ChannelCategoryNode $node, ChannelPlacementSource $source): void
    {
        $this->node = $node;
        $this->source = $source;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
