<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Marker interface for domain entities that record WHO created / last updated
 * them, as a snapshot of the actor's identifier (e-mail) at write time.
 *
 * Implementing this opts an entity into
 * {@see \App\Shared\Infrastructure\Doctrine\EventListener\BlameableAssignmentListener}:
 *   - on insert it stamps both `createdBy` and `updatedBy`,
 *   - on update it stamps `updatedBy`,
 * using the current authenticated principal's identifier (Symfony Security).
 *
 * A SNAPSHOT STRING (not a User FK) is deliberate: "who created this" is a
 * historical audit fact that must survive the user being renamed or deleted,
 * and it keeps the owning bounded context free of a hard dependency on the
 * Identity context (no cross-context association). Background writers with no
 * security context (CLI seeders, async import) leave the fields `null`.
 */
interface Blameable
{
    public function getCreatedBy(): ?string;

    public function getUpdatedBy(): ?string;

    public function stampCreatedBy(?string $actor): void;

    public function stampUpdatedBy(?string $actor): void;
}
