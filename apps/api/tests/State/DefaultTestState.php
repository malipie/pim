<?php

declare(strict_types=1);

namespace App\Tests\State;

use App\DataFixtures\Identity\PrdPermissionFixtures;
use App\Identity\Application\RbacSeeder;
use Doctrine\ORM\EntityManagerInterface;

/**
 * AUD-082 — global test state, loaded once via Foundry's `global_state`.
 *
 * Seeds the TENANT-AGNOSTIC permission catalogue exactly once per test
 * session: the legacy {@see RbacSeeder} permissions + four built-in global
 * roles, plus the granular PRD permission set ({@see PrdPermissionFixtures}).
 * With DAMA enabled, Foundry commits global state BEFORE the per-test
 * transaction and it survives every rollback (see
 * {@see \Zenstruck\Foundry\ORM\ResetDatabase\DamaDatabaseResetter}), so the
 * ~114 ApiTestCase subclasses no longer re-insert ~70 permission/role rows in
 * every single setUp().
 *
 * Both seeders are idempotent (match-by-code), so any test that still calls
 * them in its own setUp keeps working — the rows just already exist.
 *
 * Tenant-scoped data (the `demo` tenant, its PRD roles, the admin user, the
 * built-in ObjectTypes) is intentionally NOT seeded here: ~18 Integration
 * tests create their own `new Tenant('demo')` per test, so a committed global
 * `demo` tenant would collide on the unique `tenants.code`. Globalising that
 * needs a separate, wider refactor (tracked in #1745).
 */
final class DefaultTestState
{
    public function __construct(
        private readonly RbacSeeder $rbacSeeder,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(): void
    {
        $this->rbacSeeder->seed();
        new PrdPermissionFixtures()->load($this->em);
        $this->em->flush();
    }
}
