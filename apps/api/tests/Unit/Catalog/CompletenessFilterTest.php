<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Infrastructure\ApiPlatform\Filter\CompletenessFilter;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AUD-037 / W3-5.1 (#1611) — the completeness range filter must generate a
 * predicate on the indexed `completenessPct` column, NOT a non-sargable
 * `JSONB_GET_NUMERIC(o.completeness, 'pct')` function call.
 *
 * EXPLAIN on 50k rows is not reproducible against the empty dev/test DB, so
 * the regression is pinned at the query-shape level: the generated DQL
 * condition references the column (which `objects_tenant_kind_compl_idx`
 * covers) and never the JSONB function (which the index cannot serve, and
 * which read the wrong `pct` key the payload never writes).
 */
final class CompletenessFilterTest extends TestCase
{
    #[Test]
    public function generatesColumnPredicateNotJsonbFunction(): void
    {
        $capture = new CompletenessFilterTestCapture();
        $nameGenerator = $this->createStub(QueryNameGeneratorInterface::class);
        $nameGenerator->method('generateParameterName')->willReturnCallback(
            static fn (string $field): string => $field.'_p1',
        );

        new CompletenessFilter()->apply(
            $this->queryBuilderCapturing($capture),
            $nameGenerator,
            CatalogObject::class,
            null,
            ['filters' => ['completeness' => ['gte' => 80]]],
        );

        self::assertCount(1, $capture->where, 'Exactly one range predicate is added for a single operator.');
        $condition = $capture->where[0];

        self::assertStringContainsString(
            'o.completenessPct >= :completeness_gte_p1',
            $condition,
            'The filter must compare the sargable completenessPct column.',
        );
        self::assertStringNotContainsString(
            'JSONB_GET_NUMERIC',
            $condition,
            'The filter must NOT fall back to the non-indexable JSONB function.',
        );
        self::assertStringNotContainsString(
            'completeness,',
            $condition,
            'The filter must NOT read the completeness JSONB blob.',
        );

        self::assertArrayHasKey('completeness_gte_p1', $capture->params);
        self::assertSame(80, $capture->params['completeness_gte_p1'], 'Threshold binds as an int matching the smallint column.');
    }

    #[Test]
    public function multipleOperatorsEachUseTheColumn(): void
    {
        $capture = new CompletenessFilterTestCapture();
        $nameGenerator = $this->createStub(QueryNameGeneratorInterface::class);
        $nameGenerator->method('generateParameterName')->willReturnCallback(
            static fn (string $field): string => $field.'_p',
        );

        new CompletenessFilter()->apply(
            $this->queryBuilderCapturing($capture),
            $nameGenerator,
            CatalogObject::class,
            null,
            ['filters' => ['completeness' => ['gt' => 30, 'lt' => 90]]],
        );

        self::assertCount(2, $capture->where);
        foreach ($capture->where as $condition) {
            self::assertStringContainsString('o.completenessPct', $condition);
            self::assertStringNotContainsString('JSONB_GET_NUMERIC', $condition);
        }
    }

    /**
     * A QueryBuilder double that records every andWhere() condition + bound
     * parameter so the generated DQL can be asserted without a DB. The real
     * Expr is used so string casting matches Doctrine's output.
     */
    private function queryBuilderCapturing(CompletenessFilterTestCapture $capture): QueryBuilder
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('getRootAliases')->willReturn(['o']);
        $queryBuilder->method('expr')->willReturn(new Expr());
        $queryBuilder->method('andWhere')->willReturnCallback(
            static function (string $condition) use ($capture, $queryBuilder): QueryBuilder {
                $capture->where[] = $condition;

                return $queryBuilder;
            },
        );
        $queryBuilder->method('setParameter')->willReturnCallback(
            static function (string $key, mixed $value) use ($capture, $queryBuilder): QueryBuilder {
                $capture->params[$key] = $value;

                return $queryBuilder;
            },
        );

        return $queryBuilder;
    }
}

/**
 * Mutable sink for the conditions / parameters the filter pushes onto the
 * QueryBuilder double — a typed holder so PHPStan max sees concrete property
 * shapes instead of an optional-key array.
 */
final class CompletenessFilterTestCapture
{
    /** @var list<string> */
    public array $where = [];

    /** @var array<string, mixed> */
    public array $params = [];
}
