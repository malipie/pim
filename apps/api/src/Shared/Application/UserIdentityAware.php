<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Marker for security principals that expose a stable UUID identity.
 *
 * Cross-bounded-context code (Catalog, Channel, Asset…) needs to record
 * `user_id` on owned rows (`saved_views.user_id`, `smart_filter_presets.user_id`,
 * future `bulk_sessions.user_id` etc.) without depending on the concrete
 * `Identity\Domain\Entity\User` class — Deptrac enforces the boundary.
 *
 * Implemented by `App\Identity\Domain\Entity\User`. Machine principals
 * (`ApiKeyPrincipal`) do **not** implement this — controllers should treat
 * api-key paths as bot-owned (write a NULL user_id) or reject them at the
 * permission layer.
 */
interface UserIdentityAware extends UserInterface
{
    public function getId(): Uuid;
}
