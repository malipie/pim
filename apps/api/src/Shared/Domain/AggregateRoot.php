<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Base class for aggregate roots that record domain events during a unit of
 * work. Subclasses call {@see recordThat()} from inside their state-changing
 * methods; the persistence boundary later pulls the buffered events through
 * {@see pullEvents()} and forwards them to Messenger (RF-20). The pull is
 * destructive on purpose — once the events have been handed off the aggregate
 * starts fresh so a second flush in the same request doesn't replay them.
 *
 * The class deliberately stays Doctrine-free: Doctrine ORM 3 with lazy ghost
 * objects + report_fields_where_declared (see config/packages/doctrine.yaml)
 * is happy to extend mapped classes from a non-mapped base, so the XML
 * mappings under `Infrastructure/Doctrine/Orm/Mapping` don't need to know
 * about this base at all.
 *
 * Why not declare $pendingEvents `protected` and let subclasses push directly?
 * Because subclasses persisted by Doctrine get hydrated through Reflection;
 * Doctrine bypasses the constructor and never resets the array. Keeping the
 * field private and offering only `recordThat` / `pullEvents` makes the
 * lifecycle obvious and impossible to misuse.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    final protected function recordThat(DomainEvent $event): void
    {
        $this->pendingEvents[] = $event;
    }

    /**
     * Returns the events recorded since the last pull and clears the buffer.
     *
     * @return list<DomainEvent>
     */
    final public function pullEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }
}
