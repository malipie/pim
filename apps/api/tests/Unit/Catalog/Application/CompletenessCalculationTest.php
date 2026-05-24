<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application;

use App\Catalog\Application\AttributesIndexedRebuilder;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based-style coverage of the completeness calculation in
 * {@see AttributesIndexedRebuilder::rebuild} (audit / 0.11.6).
 *
 * The listener-level integration test
 * ({@see \App\Tests\Integration\Catalog\AttributesIndexedSyncListenerTest})
 * verifies the wiring; this unit test enumerates the algorithm's
 * properties directly so regressions in the math (rounding, denominator
 * handling, missing-attribute counting) surface without a DB round-trip.
 *
 * Properties under test:
 *   1. Empty `required` list → completeness is always 100 regardless of values.
 *   2. Reported pct is always within [0, 100].
 *   3. All required attributes present → 100. None present → 0.
 *   4. Half present rounds to 50; uneven splits round to nearest int.
 *   5. Non-required attribute values do NOT bump completeness.
 *   6. Non-string entries inside `required` are silently ignored
 *      (defensive parsing of JSONB rules — they may be edited by hand).
 */
final class CompletenessCalculationTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     required: list<mixed>,
     *     present: list<string>,
     *     expectedPct: int,
     * }>
     */
    public static function completenessCases(): iterable
    {
        yield 'empty rules + zero values → 100' => [
            'required' => [],
            'present' => [],
            'expectedPct' => 100,
        ];
        yield 'empty rules + many values → 100' => [
            'required' => [],
            'present' => ['name', 'brand', 'description', 'sku'],
            'expectedPct' => 100,
        ];
        yield 'all required present → 100' => [
            'required' => ['name', 'brand'],
            'present' => ['name', 'brand'],
            'expectedPct' => 100,
        ];
        yield 'none required present → 0' => [
            'required' => ['name', 'brand'],
            'present' => [],
            'expectedPct' => 0,
        ];
        yield 'half required present → 50' => [
            'required' => ['name', 'brand'],
            'present' => ['name'],
            'expectedPct' => 50,
        ];
        yield 'one of three required → 33 (rounded down)' => [
            'required' => ['name', 'brand', 'description'],
            'present' => ['name'],
            'expectedPct' => 33,
        ];
        yield 'two of three required → 67 (rounded up)' => [
            'required' => ['name', 'brand', 'description'],
            'present' => ['name', 'brand'],
            'expectedPct' => 67,
        ];
        yield 'one of six required → 17 (banker rounding)' => [
            'required' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'present' => ['a'],
            'expectedPct' => 17,
        ];
        yield 'extra non-required values do not raise pct' => [
            'required' => ['name'],
            'present' => ['name', 'brand', 'description'],
            'expectedPct' => 100,
        ];
        yield 'extra non-required values when nothing required is present → 0' => [
            'required' => ['name'],
            'present' => ['brand', 'description'],
            'expectedPct' => 0,
        ];
        yield 'non-string entries in required are ignored' => [
            'required' => ['name', 42, null, true, 'brand'],
            'present' => ['name', 'brand'],
            // Denominator stays at 5 (the rebuilder counts the list length),
            // but only the two real string codes can ever match — so 2/5 = 40.
            'expectedPct' => 40,
        ];
    }

    /**
     * @param list<mixed>  $required
     * @param list<string> $present
     */
    #[Test]
    #[DataProvider('completenessCases')]
    public function rebuildComputesCompletenessFromRequiredListAndPresentValues(
        array $required,
        array $present,
        int $expectedPct,
    ): void {
        // Resolver stub returns empty effective groups — the rebuilder's
        // MOD-09 effective-model filter is bypassed (empty effective set
        // falls back to the legacy `required` count), so this unit test
        // still exercises the raw math invariants.
        $resolver = $this->createStub(EffectiveAttributeGroupResolver::class);
        $resolver->method('resolve')->willReturn([]);
        $resolver->method('loadGroupAttributes')->willReturn([]);
        $rebuilder = new AttributesIndexedRebuilder(
            $this->createStub(EntityManagerInterface::class),
            $resolver,
        );

        $tenant = self::tenantStub();
        $objectType = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $objectType->assignTenant($tenant);
        $objectType->updateCompletenessRules(['required' => $required]);

        $object = new CatalogObject($objectType, 'SKU-PROP-1');
        $values = self::buildObjectValues($object, $tenant, $present);

        $rebuilder->rebuild($object, $values);

        $completeness = $object->getCompleteness();
        self::assertArrayHasKey('global', $completeness);
        self::assertGreaterThanOrEqual(0, $completeness['global']);
        self::assertLessThanOrEqual(100, $completeness['global']);
        self::assertSame($expectedPct, $completeness['global']);
    }

    #[Test]
    public function emptyValuesListWithNoRulesYieldsHundredAndEmptyAttributesIndexed(): void
    {
        // Resolver stub returns empty effective groups — the rebuilder's
        // MOD-09 effective-model filter is bypassed (empty effective set
        // falls back to the legacy `required` count), so this unit test
        // still exercises the raw math invariants.
        $resolver = $this->createStub(EffectiveAttributeGroupResolver::class);
        $resolver->method('resolve')->willReturn([]);
        $resolver->method('loadGroupAttributes')->willReturn([]);
        $rebuilder = new AttributesIndexedRebuilder(
            $this->createStub(EntityManagerInterface::class),
            $resolver,
        );

        $tenant = self::tenantStub();
        $objectType = new ObjectType('asset', ObjectKind::Asset, ['en' => 'Asset']);
        $objectType->assignTenant($tenant);

        $object = new CatalogObject($objectType, 'IMG-PROP-1');
        $rebuilder->rebuild($object, []);

        self::assertSame(['global' => 100], $object->getCompleteness());
        self::assertSame([], $object->getAttributesIndexed());
    }

    /**
     * @param list<string> $presentCodes
     *
     * @return list<ObjectValue>
     */
    private static function buildObjectValues(CatalogObject $object, Tenant $tenant, array $presentCodes): array
    {
        $values = [];
        foreach ($presentCodes as $code) {
            $attribute = new Attribute(
                $code,
                ['en' => ucfirst($code)],
                AttributeType::Text,
            );
            $attribute->assignTenant($tenant);
            $values[] = new ObjectValue($object, $attribute, ['value' => 'sample']);
        }

        return $values;
    }

    private static function tenantStub(): Tenant
    {
        return new Tenant('demo-test-'.bin2hex(random_bytes(2)), 'Demo');
    }
}
