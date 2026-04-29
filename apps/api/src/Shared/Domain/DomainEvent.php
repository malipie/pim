<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use DateTimeImmutable;

/**
 * Marker interface for in-process domain events recorded by aggregates.
 *
 * Two layers above this one round out the picture:
 *   - {@see AggregateRoot} buffers events during a unit of work and lets the
 *     persistence boundary pull them out in {@see AggregateRoot::pullEvents()};
 *   - the integration-event DTOs that travel cross-bounded-context land in
 *     each BC's `Contracts/Event/` namespace (RF-16+) and themselves implement
 *     this interface — Domain emits its own change facts, the Contracts layer
 *     re-publishes the ones that other contexts care about.
 *
 * The interface is intentionally narrow: events are immutable value objects
 * (`final readonly class`) that carry the aggregate identity and the wall
 * clock at which the change was decided. Anything else is event-specific and
 * belongs on the concrete class.
 */
interface DomainEvent
{
    /**
     * Wall clock at which the aggregate decided the change. Set in the event
     * constructor — never re-derived from system time inside subscribers.
     */
    public function occurredOn(): DateTimeImmutable;

    /**
     * Stable identifier of the event class for routing / logging — by
     * convention the unqualified class name in dot.case (e.g. `object.created`).
     * The default in concrete events should be a const.
     */
    public function eventName(): string;

    /**
     * Aggregate identity as RFC-4122 string. Concrete events store the typed
     * Uuid alongside; the string representation lives here so the dispatcher
     * can stamp Messenger envelopes without unwrapping.
     */
    public function aggregateId(): string;
}
