<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Application\Query\GetObjectFormSchema\GetObjectFormSchemaHandler;
use App\Catalog\Application\Query\GetObjectFormSchema\ObjectFormSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level guards for the public surface of UI-08.4 (#259) — the
 * caching contract + projection shape. Domain-level resolver behaviour
 * (M:N joins, ancestor walk, dedup priority) is exercised by the
 * integration test that needs a real ORM round trip.
 */
final class EffectiveAttributeGroupResolverTest extends TestCase
{
    #[Test]
    public function cacheTagAndTtlAreCommittedConstants(): void
    {
        // The Doctrine listener invalidates by tag + the controller
        // documents the TTL — both are stable contracts that should not
        // drift silently. Pin them here.
        self::assertSame('pim_form_schema', GetObjectFormSchemaHandler::CACHE_TAG);
        self::assertSame(300, GetObjectFormSchemaHandler::CACHE_TTL_SECONDS);
    }

    #[Test]
    public function objectFormSchemaProjectionMatchesExpectedKeys(): void
    {
        $schema = new ObjectFormSchema(
            objectId: '01900000-0000-7000-8000-000000000001',
            objectType: [
                'id' => '01900000-0000-7000-8000-aaaaaaaaaaaa',
                'code' => 'product',
                'kind' => 'product',
                'label' => ['en' => 'Product'],
            ],
            effectiveGroups: [
                [
                    'id' => '01900000-0000-7000-8000-bbbbbbbbbbbb',
                    'code' => 'audit',
                    'label' => ['en' => 'Audit'],
                    'description' => null,
                    'icon' => 'ShieldCheck',
                    'color' => '#64748B',
                    'is_system_group' => true,
                    'auto_attached' => true,
                    'position' => 0,
                    'attributes' => [],
                ],
            ],
        );

        $payload = $schema->toArray();
        self::assertSame(['objectId', 'objectType', 'effectiveGroups'], array_keys($payload));
        $type = $payload['objectType'];
        self::assertIsArray($type);
        self::assertSame('product', $type['kind']);
        $groups = $payload['effectiveGroups'];
        self::assertIsArray($groups);
        self::assertCount(1, $groups);
        self::assertIsArray($groups[0]);
        self::assertTrue($groups[0]['is_system_group']);
    }
}
