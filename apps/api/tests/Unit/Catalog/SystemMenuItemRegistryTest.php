<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Catalog\Domain\SystemMenuItemRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VIEW-08 (#427) — registry of in-code system menu items.
 */
final class SystemMenuItemRegistryTest extends TestCase
{
    #[Test]
    public function registryHasSevenSystemItems(): void
    {
        self::assertCount(7, SystemMenuItemRegistry::items());
    }

    #[Test]
    public function settingsAndModelingAreProtected(): void
    {
        self::assertTrue(SystemMenuItemRegistry::isProtected('settings'));
        self::assertTrue(SystemMenuItemRegistry::isProtected('modeling'));
        self::assertFalse(SystemMenuItemRegistry::isProtected('dashboard'));
        self::assertFalse(SystemMenuItemRegistry::isProtected('multimedia'));
    }

    #[Test]
    public function workflowIsComingSoon(): void
    {
        $workflow = SystemMenuItemRegistry::get('workflow');
        self::assertNotNull($workflow);
        self::assertTrue($workflow['comingSoon']);
        self::assertNull($workflow['route']);
    }

    #[Test]
    public function existsRecognisesAllRegisteredKeys(): void
    {
        foreach (array_keys(SystemMenuItemRegistry::items()) as $key) {
            self::assertTrue(SystemMenuItemRegistry::exists($key), $key.' should exist');
        }
        self::assertFalse(SystemMenuItemRegistry::exists('not-a-key'));
    }

    #[Test]
    public function defaultOrderListsSystemItemsWithoutDashboardSlot(): void
    {
        $order = SystemMenuItemRegistry::defaultOrder();
        self::assertSame('dashboard', $order[0]);
        self::assertContains('settings', $order);
        self::assertContains('modeling', $order);
        // Services intentionally absent — operator adds it as a custom OT later.
        self::assertNotContains('services', $order);
    }
}
