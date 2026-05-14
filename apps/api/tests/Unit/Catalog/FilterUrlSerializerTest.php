<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Application\Filter\FilterDslResolver;
use App\Catalog\Application\Filter\FilterUrlSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * VIEW-10 (#538) — `FilterUrlSerializer` round-trip behaviour.
 *
 * Coverage:
 *   - shorthand op → canonical (`lt` → `<`, `contains` → `contains`).
 *   - single-value param → `=` condition.
 *   - comma-separated value → `IN` condition.
 *   - explicit op + value array shapes.
 *   - between via tuple value or comma-separated string.
 *   - base64 round-trip preserves DSL.
 *   - blob > 4096 bytes throws 413.
 *   - nested groups in toUrlParams reject with 400.
 */
final class FilterUrlSerializerTest extends TestCase
{
    private FilterUrlSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new FilterUrlSerializer(new FilterDslResolver());
    }

    public function testSingleValueDefaultsToEquals(): void
    {
        $dsl = $this->serializer->fromUrlParams(['brand' => 'Festo']);
        self::assertSame(['attr' => 'brand', 'op' => '=', 'value' => 'Festo'], $dsl);
    }

    public function testCommaSeparatedPromotesToIn(): void
    {
        $dsl = $this->serializer->fromUrlParams(['brand' => 'Festo,Bosch']);
        self::assertSame([
            'attr' => 'brand',
            'op' => 'IN',
            'value' => ['Festo', 'Bosch'],
        ], $dsl);
    }

    public function testShorthandOperatorExpands(): void
    {
        $dsl = $this->serializer->fromUrlParams([
            'completeness_pct' => ['op' => 'lt', 'value' => '50'],
        ]);
        self::assertSame(['attr' => 'completeness_pct', 'op' => '<', 'value' => '50'], $dsl);
    }

    public function testMultipleParamsBuildsAndGroup(): void
    {
        $dsl = $this->serializer->fromUrlParams([
            'brand' => 'Festo',
            'completeness_pct' => ['op' => 'lt', 'value' => '50'],
        ]);

        self::assertSame('AND', $dsl['operator']);
        self::assertIsArray($dsl['conditions']);
        self::assertCount(2, $dsl['conditions']);
    }

    public function testBetweenAcceptsCsvString(): void
    {
        $dsl = $this->serializer->fromUrlParams([
            'price' => ['op' => 'between', 'value' => '100,500'],
        ]);

        self::assertSame(['attr' => 'price', 'op' => 'between', 'value' => ['100', '500']], $dsl);
    }

    public function testBetweenRejectsSingleValue(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->serializer->fromUrlParams(['price' => ['op' => 'between', 'value' => '100']]);
    }

    public function testBase64RoundTripPreservesDsl(): void
    {
        $dsl = [
            'operator' => 'AND',
            'conditions' => [
                ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
                ['attr' => 'completeness_pct', 'op' => '<', 'value' => 50],
            ],
        ];
        $blob = $this->serializer->toBase64($dsl);
        $decoded = $this->serializer->fromBase64($blob);

        self::assertSame($dsl, $decoded);
    }

    public function testBlobTooLongThrows413(): void
    {
        try {
            $this->serializer->fromBase64(str_repeat('A', FilterUrlSerializer::MAX_BLOB_BYTES + 10));
            self::fail('Expected HttpException with status 413');
        } catch (HttpException $e) {
            self::assertSame(413, $e->getStatusCode());
        }
    }

    public function testInvalidBase64Throws400(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->serializer->fromBase64('!!!not-base64!!!');
    }

    public function testToUrlParamsRejectsNestedGroups(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->serializer->toUrlParams([
            'operator' => 'AND',
            'conditions' => [
                ['operator' => 'OR', 'conditions' => [
                    ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
                ]],
            ],
        ]);
    }

    public function testToUrlParamsFlatRoundTrip(): void
    {
        $dsl = [
            'operator' => 'AND',
            'conditions' => [
                ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
                ['attr' => 'completeness_pct', 'op' => '<', 'value' => 50],
            ],
        ];

        $params = $this->serializer->toUrlParams($dsl);
        self::assertArrayHasKey('brand', $params);
        self::assertArrayHasKey('completeness_pct', $params);
        self::assertSame('=', $params['brand']['op']);
        self::assertSame('<', $params['completeness_pct']['op']);
    }
}
