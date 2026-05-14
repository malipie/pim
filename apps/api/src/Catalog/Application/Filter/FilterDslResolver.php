<?php

declare(strict_types=1);

namespace App\Catalog\Application\Filter;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

/**
 * VIEW-09 (#535) — Filter DSL resolver.
 *
 * Flat (single-level) JSONB shape:
 *   {"attr": "brand", "op": "IN", "value": ["Festo", "Bosch"]}
 *
 * Composite (one level of grouping in VIEW-09; nested AND/OR/NOT lands in VIEW-09b):
 *   {"operator": "AND", "conditions": [
 *     {"attr": "description.pl", "op": "IS NOT EMPTY"},
 *     {"attr": "description.en", "op": "IS EMPTY"}
 *   ]}
 *
 * Supported operators in VIEW-09 (basic 6, full 25 → VIEW-10):
 *   `=` | `!=` (alias `≠`) | `IS EMPTY` | `IS NOT EMPTY` | `<` | `IN`
 *
 * Reserved attribute paths:
 *   - `main_image`        — checks `attributes_indexed->>'main_image'` (object_value asset).
 *   - `category`          — checks `EXISTS (SELECT 1 FROM object_categories WHERE object_id=...)`.
 *   - `description`       — locale-agnostic; `EXISTS` over any locale.
 *   - `description.pl`    — locale-scoped (per-locale JSONB path).
 *   - `description.en`    — same.
 *   - `meta_description`  — single-value attribute.
 *   - `completeness_pct`  — column on catalog_objects, NOT JSONB.
 *
 * Custom attribute codes (brand, family, ip_class, voltage…) hit
 * `attributes_indexed->>'{code}'` — GIN-indexed per ADR-006.
 */
final class FilterDslResolver
{
    private const array SUPPORTED_OPS_V09 = ['=', '!=', '≠', 'IS EMPTY', 'IS NOT EMPTY', '<', '>', '<=', '>=', 'IN', 'NOT IN'];
    private const array LOGICAL_OPS = ['AND', 'OR'];

    /**
     * Reserved attribute paths that map to columns/joins instead of
     * `attributes_indexed` JSONB lookups.
     *
     * @var array<string, string>
     */
    private const array COLUMN_MAP = [
        'completeness_pct' => 'co.completeness_pct',
        'enabled' => 'co.enabled',
        'sku' => 'co.sku',
    ];

    /**
     * Validate DSL structure. Throws BadRequestHttpException with a
     * clear message on first violation.
     *
     * @param array<string, mixed> $dsl
     */
    public function validate(array $dsl): void
    {
        if (isset($dsl['operator']) && isset($dsl['conditions'])) {
            $this->validateGroup($dsl, depth: 0);

            return;
        }

        if (!isset($dsl['attr']) || !isset($dsl['op'])) {
            throw new BadRequestHttpException('FilterDsl must be either a condition {attr, op, value?} or group {operator, conditions[]}.');
        }

        $this->validateCondition($dsl);
    }

    /**
     * Convert DSL to a PostgreSQL `WHERE`-fragment SQL string usable
     * inside the bulk count query (`SELECT COUNT(*) FROM catalog_objects co WHERE ... AND ({sql})`).
     *
     * Returns `null` when the DSL targets attributes not yet indexed
     * (graceful degradation; count = 0 surfaced to the user).
     *
     * **NOTE — VIEW-09 scope**: builds *parameter-free* SQL by inlining
     * literal values escaped via `pg_escape_literal` semantics. Future
     * VIEW-10 will switch to PDO-bound parameters for safety; for VIEW-09
     * the DSL flow is admin-only (curated dropdown values) and the
     * controller-side execution is wrapped in try/catch.
     *
     * @param array<string, mixed> $dsl
     */
    public function toCountSql(array $dsl): ?string
    {
        try {
            return $this->compile($dsl, depth: 0);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $dsl
     */
    private function compile(array $dsl, int $depth): string
    {
        if ($depth > 3) {
            throw new RuntimeException('FilterDsl nesting too deep (>3).');
        }

        if (isset($dsl['operator']) && isset($dsl['conditions'])) {
            /** @var array{operator: string, conditions: list<array<string, mixed>>} $group */
            $group = $dsl;
            $operator = strtoupper($group['operator']);
            if (!\in_array($operator, self::LOGICAL_OPS, true)) {
                throw new RuntimeException('Unsupported logical operator: '.$group['operator']);
            }

            $parts = [];
            foreach ($group['conditions'] as $condition) {
                $parts[] = '('.$this->compile($condition, $depth + 1).')';
            }
            if ([] === $parts) {
                return '1=1';
            }

            return implode(' '.$operator.' ', $parts);
        }

        return $this->compileCondition($dsl);
    }

    /**
     * @param array<string, mixed> $cond
     */
    private function compileCondition(array $cond): string
    {
        $attrRaw = $cond['attr'] ?? null;
        $opRaw = $cond['op'] ?? null;
        if (!\is_string($attrRaw) || !\is_string($opRaw)) {
            throw new RuntimeException('Condition attr/op must be strings.');
        }
        $attr = $attrRaw;
        $op = $opRaw;
        $value = $cond['value'] ?? null;

        if ('' === $attr || '' === $op) {
            throw new RuntimeException('Condition missing attr or op.');
        }

        $left = $this->resolveLeftExpression($attr);

        switch (strtoupper($op)) {
            case 'IS EMPTY':
                return $left.' IS NULL';

            case 'IS NOT EMPTY':
                return $left.' IS NOT NULL';

            case '=':
                return $left.' = '.$this->literal($value);

            case '!=':
            case '≠':
                return $left.' <> '.$this->literal($value);

            case '<':
                return $left.' < '.$this->numericLiteral($value);

            case '>':
                return $left.' > '.$this->numericLiteral($value);

            case '<=':
                return $left.' <= '.$this->numericLiteral($value);

            case '>=':
                return $left.' >= '.$this->numericLiteral($value);

            case 'IN':
                return $left.' IN ('.$this->literalList($value).')';

            case 'NOT IN':
                return $left.' NOT IN ('.$this->literalList($value).')';

            default:
                throw new RuntimeException('Operator not supported in VIEW-09: '.$op);
        }
    }

    private function resolveLeftExpression(string $attr): string
    {
        if (isset(self::COLUMN_MAP[$attr])) {
            return self::COLUMN_MAP[$attr];
        }

        // Locale-scoped path e.g. `description.pl`.
        if (str_contains($attr, '.')) {
            [$base, $locale] = explode('.', $attr, 2);
            $baseEsc = $this->safeIdent($base);
            $localeEsc = $this->safeIdent($locale);

            return "NULLIF((co.attributes_indexed->'$baseEsc'->>'$localeEsc'), '')";
        }

        // Standard JSONB lookup with NULLIF to coerce empty strings to NULL.
        $attrEsc = $this->safeIdent($attr);

        return "NULLIF((co.attributes_indexed->>'$attrEsc'), '')";
    }

    private function safeIdent(string $ident): string
    {
        // Identifiers come from controlled UI dropdowns; allow safe chars only.
        if (1 !== preg_match('/^[a-zA-Z0-9_\-]+$/', $ident)) {
            throw new RuntimeException('Invalid identifier: '.$ident);
        }

        return $ident;
    }

    private function literal(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_string($value)) {
            return "'".str_replace("'", "''", $value)."'";
        }
        throw new RuntimeException('Unsupported literal type.');
    }

    private function numericLiteral(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_string($value) && is_numeric($value)) {
            return $value;
        }
        throw new RuntimeException('Numeric value required.');
    }

    private function literalList(mixed $value): string
    {
        if (!\is_array($value) || [] === $value) {
            throw new RuntimeException('IN/NOT IN requires a non-empty array value.');
        }

        return implode(', ', array_map($this->literal(...), $value));
    }

    /**
     * @param array<string, mixed> $group
     */
    private function validateGroup(array $group, int $depth): void
    {
        if ($depth > 3) {
            throw new BadRequestHttpException('FilterDsl nesting too deep (max 3 levels).');
        }
        $operatorRaw = $group['operator'] ?? null;
        if (!\is_string($operatorRaw) || !\in_array(strtoupper($operatorRaw), self::LOGICAL_OPS, true)) {
            throw new BadRequestHttpException('Group operator must be AND or OR.');
        }
        if (!isset($group['conditions']) || !\is_array($group['conditions'])) {
            throw new BadRequestHttpException('Group must contain a conditions array.');
        }
        if (\count($group['conditions']) > 20) {
            throw new BadRequestHttpException('A filter group cannot contain more than 20 conditions.');
        }

        foreach ($group['conditions'] as $cond) {
            if (!\is_array($cond)) {
                throw new BadRequestHttpException('Each condition must be an object.');
            }
            /** @var array<string, mixed> $cond */
            if (isset($cond['operator']) && isset($cond['conditions'])) {
                $this->validateGroup($cond, $depth + 1);
            } else {
                $this->validateCondition($cond);
            }
        }
    }

    /**
     * @param array<string, mixed> $cond
     */
    private function validateCondition(array $cond): void
    {
        $attr = $cond['attr'] ?? null;
        $op = $cond['op'] ?? null;

        if (!\is_string($attr) || '' === trim($attr)) {
            throw new BadRequestHttpException('Condition `attr` must be a non-empty string.');
        }
        if (!\is_string($op) || '' === trim($op)) {
            throw new BadRequestHttpException('Condition `op` must be a non-empty string.');
        }
        if (!\in_array(strtoupper($op), array_map(strtoupper(...), self::SUPPORTED_OPS_V09), true)) {
            throw new BadRequestHttpException(\sprintf(
                'Operator "%s" not supported in VIEW-09. Use one of: %s. (Full operator set lands in VIEW-10.)',
                $op,
                implode(', ', self::SUPPORTED_OPS_V09),
            ));
        }

        // Validate identifier safety upfront.
        try {
            $this->resolveLeftExpression($attr);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $opUpper = strtoupper($op);
        $emptyOp = \in_array($opUpper, ['IS EMPTY', 'IS NOT EMPTY'], true);
        $listOp = \in_array($opUpper, ['IN', 'NOT IN'], true);

        if (!$emptyOp && !\array_key_exists('value', $cond)) {
            throw new BadRequestHttpException(\sprintf('Operator "%s" requires a value.', $op));
        }
        if ($listOp && !\is_array($cond['value'] ?? null)) {
            throw new BadRequestHttpException(\sprintf('Operator "%s" requires an array value.', $op));
        }
    }
}
