<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-06 (#1289) — maps a master category to one-or-many channel navigation
 * nodes, so auto-assignment (CHC-07) can place every product of that category
 * onto the channel without per-product manual work.
 *
 * `masterCategoryId` is a soft FK to a master `objects` (kind=category) row,
 * kept as a bare Uuid so the Channel context never imports Catalog entities
 * (deptrac). `channelNodeIds` is the M:N target set on the channel side
 * (one master → many channel nodes), stored as a JSONB list of node ids.
 */
class ChannelCategoryNodeMapping implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Channel $channel;

    private Uuid $masterCategoryId;

    /**
     * @var list<string> RFC-4122 ids of ChannelCategoryNode targets
     */
    private array $channelNodeIds;

    /**
     * @param list<string> $channelNodeIds
     */
    public function __construct(
        Channel $channel,
        Uuid $masterCategoryId,
        array $channelNodeIds,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->channel = $channel;
        $this->masterCategoryId = $masterCategoryId;
        $this->channelNodeIds = $channelNodeIds;
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

    public function getMasterCategoryId(): Uuid
    {
        return $this->masterCategoryId;
    }

    /**
     * @return list<string>
     */
    public function getChannelNodeIds(): array
    {
        return $this->channelNodeIds;
    }

    /**
     * @param list<string> $channelNodeIds
     */
    public function replaceNodes(array $channelNodeIds): void
    {
        $this->channelNodeIds = $channelNodeIds;
    }
}
