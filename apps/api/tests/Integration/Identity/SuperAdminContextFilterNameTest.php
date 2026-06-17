<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\SuperAdmin\SuperAdminContext;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-026 / W1-3 — black-box guard that {@see SuperAdminContext} toggles
 * the SAME Doctrine filter that `doctrine.yaml` actually registers.
 *
 * Why an integration test rather than the existing mocked unit suite
 * ({@see \App\Tests\Unit\Identity\Application\SuperAdmin\SuperAdminContextTest}):
 * those mock `FilterCollection` and assert the call happens *with*
 * `SuperAdminContext::FILTER_NAME` — so they pass for ANY value of the
 * constant, including the wrong one. They could not catch the bug where
 * the constant (`tenant_filter`) drifted from the registered filter name
 * (`tenant`).
 *
 * Here the EntityManager comes from the real container, so its filter
 * registry is the one built from `doctrine.yaml`. Enabling the registered
 * filter and asserting `useCrossTenantMode()` disables it proves the names
 * agree end to end.
 *
 * RED (pre-fix, FILTER_NAME='tenant_filter'): `useCrossTenantMode()` calls
 * `isEnabled('tenant_filter')` → false → never disables the real `tenant`
 * filter, so it stays enabled inside the cross-tenant scope. The break-glass
 * "isolation off" invariant is a lie.
 * GREEN (post-fix, FILTER_NAME='tenant'): the registered filter is disabled
 * inside the scope and restored afterwards.
 */
final class SuperAdminContextFilterNameTest extends KernelTestCase
{
    /**
     * The literal name `doctrine.yaml` registers the TenantFilter under.
     * Hard-coded on purpose: the test must fail if EITHER the YAML name OR
     * the SuperAdminContext constant moves without the other following.
     */
    private const string REGISTERED_FILTER_NAME = 'tenant';

    #[Test]
    public function crossTenantModeDisablesTheRegisteredTenantFilter(): void
    {
        self::bootKernel();
        $em = $this->em();
        $context = self::getContainer()->get(SuperAdminContext::class);
        self::assertInstanceOf(SuperAdminContext::class, $context);

        $filters = $em->getFilters();
        // Establish the realistic request-time precondition: the tenant
        // filter is enabled. (TenantFilterConfigurator does this once a
        // tenant is known.) A parameter is not required to assert
        // enabled/disabled state.
        if (!$filters->isEnabled(self::REGISTERED_FILTER_NAME)) {
            $filters->enable(self::REGISTERED_FILTER_NAME);
        }
        self::assertTrue(
            $filters->isEnabled(self::REGISTERED_FILTER_NAME),
            'Precondition: the registered tenant filter must be enabled before entering cross-tenant mode.',
        );

        $insideEnabled = null;
        $context->runCrossTenant(Uuid::v7(), static function () use ($filters, &$insideEnabled): void {
            $insideEnabled = $filters->isEnabled(self::REGISTERED_FILTER_NAME);
        });

        // The core invariant: inside the break-glass closure the app-layer
        // tenant filter is actually OFF. Pre-fix this is true (bug) because
        // the constant pointed at a non-existent filter name.
        self::assertFalse(
            $insideEnabled,
            'Inside cross-tenant mode the registered "tenant" filter must be disabled; '
            .'a true here means SuperAdminContext::FILTER_NAME drifted from doctrine.yaml.',
        );

        // And it is restored afterwards so the cross-tenant view does not
        // leak past the closure (worker reuse).
        self::assertTrue(
            $filters->isEnabled(self::REGISTERED_FILTER_NAME),
            'After cross-tenant mode the tenant filter must be re-enabled.',
        );

        // Clean state for any sibling test reusing the kernel's EM — the
        // filter is enabled here (asserted above), so disable it directly.
        $filters->disable(self::REGISTERED_FILTER_NAME);
    }

    /**
     * AUD-026 — `reset()` (Symfony `kernel.reset`) must clear an active
     * cross-tenant mode so a thrown exception that skipped the finally, or
     * a half-finished CLI run, cannot leak the super-admin context into the
     * next request served by the same FrankenPHP worker.
     */
    #[Test]
    public function resetClearsActiveCrossTenantState(): void
    {
        self::bootKernel();
        $context = self::getContainer()->get(SuperAdminContext::class);
        self::assertInstanceOf(SuperAdminContext::class, $context);

        $context->useCrossTenantMode(Uuid::v7());
        self::assertTrue($context->isActive(), 'Context is active after entering cross-tenant mode.');

        $context->reset();

        self::assertFalse($context->isActive(), 'reset() must clear the active super-admin context.');
        self::assertNull($context->activeSuperAdminId(), 'reset() must clear the active super-admin id.');
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
