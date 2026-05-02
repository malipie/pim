<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Entity\AttributeGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * VIEW-03 (#375) — covers the three behavior flags surfaced by the
 * NewAttributeGroupView mockup (`Wymagana sekcja` / `Współdzielona`
 * / `Conditional visibility`). Asserts:
 *   - defaults match the mockup checkboxes (false / true / false),
 *   - getters/setters round-trip without invariant violations,
 *   - constructor accepts explicit values for fixture/seeder usage.
 */
final class AttributeGroupBehaviorFlagsTest extends TestCase
{
    #[Test]
    public function defaultsMatchMockupCheckboxes(): void
    {
        $group = new AttributeGroup('marketing', ['pl' => 'Marketing']);

        self::assertFalse($group->isRequiredSection(), 'Wymagana sekcja default off');
        self::assertTrue($group->isShared(), 'Współdzielona default on (matches existing implicit shareability)');
        self::assertFalse($group->hasConditionalVisibility(), 'Conditional visibility default off');
    }

    #[Test]
    public function settersRoundTrip(): void
    {
        $group = new AttributeGroup('marketing', ['pl' => 'Marketing']);

        $group->setRequiredSection(true);
        $group->setShared(false);
        $group->setConditionalVisibility(true);

        self::assertTrue($group->isRequiredSection());
        self::assertFalse($group->isShared());
        self::assertTrue($group->hasConditionalVisibility());

        $group->setRequiredSection(false);
        $group->setShared(true);
        $group->setConditionalVisibility(false);

        self::assertFalse($group->isRequiredSection());
        self::assertTrue($group->isShared());
        self::assertFalse($group->hasConditionalVisibility());
    }

    #[Test]
    public function constructorAcceptsExplicitFlagsForFixtures(): void
    {
        $group = new AttributeGroup(
            code: 'wymagania-medyczne',
            label: ['pl' => 'Wymagania medyczne'],
            position: 5,
            id: null,
            description: ['pl' => 'Atrybuty specyficzne dla usług medycznych'],
            icon: '🩺',
            color: '#ef4444',
            isSystemGroup: false,
            autoAttached: false,
            isRequiredSection: true,
            isShared: false,
            hasConditionalVisibility: true,
        );

        self::assertTrue($group->isRequiredSection());
        self::assertFalse($group->isShared());
        self::assertTrue($group->hasConditionalVisibility());
    }
}
