<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Schedule;

use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Message\InboundSyncMessage;
use App\Integration\Generic\Domain\Message\OutboundSyncMessage;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Drives a {@see SyncBinding}'s schedule (ADR-0022, epic APIC, ticket APIC-P3-09):
 * keeps its `nextRun` up to date and fires the actual sync leg(s).
 *
 * `computeNextRun()` refreshes the stored next-run from the cron expression
 * (jittered per tenant); a binding with no — or an invalid — cron is left with a
 * null `nextRun` (manual-only). `dispatch()` enqueues the per-direction sync
 * message(s) on the `import` transport and then advances `nextRun` so the
 * binding rolls forward to its next slot. Bidirectional bindings fire both legs;
 * the inbound/outbound handlers + {@see \App\Integration\Generic\Application\Sync\ConflictResolver}
 * reconcile any overlap.
 */
final readonly class SyncScheduleDispatcher
{
    public function __construct(
        private SyncScheduleCalculator $calculator,
        private SyncBindingRepositoryInterface $bindings,
        private MessageBusInterface $bus,
    ) {
    }

    public function computeNextRun(SyncBinding $binding, ?DateTimeImmutable $from = null): void
    {
        $schedule = $binding->getSchedule();
        if (null === $schedule || !$this->calculator->isValid($schedule)) {
            $binding->setNextRun(null);
            $this->bindings->save($binding);

            return;
        }

        $binding->setNextRun($this->calculator->nextRunWithJitter($schedule, $this->jitterSeed($binding), $from));
        $this->bindings->save($binding);
    }

    /**
     * Fire the binding now: enqueue its sync leg(s) and roll `nextRun` forward.
     * `$dryRun` previews the outbound push (builds + logs payloads, no remote
     * call) — see {@see OutboundSyncMessage}.
     */
    public function dispatch(SyncBinding $binding, bool $dryRun = false): void
    {
        $tenantId = $this->tenantId($binding);
        $direction = $binding->getDirection();

        if ($direction->readsRemote()) {
            $this->bus->dispatch(new InboundSyncMessage($binding->getId(), $tenantId));
        }
        if ($direction->writesRemote()) {
            $this->bus->dispatch(new OutboundSyncMessage($binding->getId(), $tenantId, $dryRun));
        }

        $this->computeNextRun($binding);
    }

    private function tenantId(SyncBinding $binding): Uuid
    {
        $tenant = $binding->getTenant();
        if (null === $tenant) {
            throw new LogicException('Cannot dispatch a sync for a binding without an assigned tenant.');
        }

        return $tenant->getId();
    }

    /**
     * Seed the jitter on the tenant id so every binding of a tenant spreads off
     * the same offset; fall back to the binding id before the tenant is assigned.
     */
    private function jitterSeed(SyncBinding $binding): string
    {
        return $binding->getTenant()?->getId()->toRfc4122() ?? $binding->getId()->toRfc4122();
    }
}
