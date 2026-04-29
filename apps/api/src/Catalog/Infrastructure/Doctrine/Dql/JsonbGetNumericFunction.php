<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Postgres `((field ->> 'key')::numeric)` as a DQL function.
 *
 * Distinct from {@see JsonbGetTextFunction} because Doctrine ORM 3 has
 * no portable `CAST(... AS DECIMAL)` in DQL — comparisons against a
 * JSONB value have to be cast in the SQL layer to behave numerically
 * (text compare returns `'30' > '80' = true`, which silently
 * mis-orders results).
 *
 * Powers the `?completeness[gt]=80` range query in `CompletenessFilter`
 * (#43): `JSONB_GET_NUMERIC(o.completeness, 'pct') > :threshold`.
 */
final class JsonbGetNumericFunction extends FunctionNode
{
    private ?Node $field = null;
    private ?Node $key = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $field = $parser->ArithmeticPrimary();
        \assert($field instanceof Node);
        $this->field = $field;
        $parser->match(TokenType::T_COMMA);
        $key = $parser->ArithmeticPrimary();
        \assert($key instanceof Node);
        $this->key = $key;
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        \assert($this->field instanceof Node && $this->key instanceof Node);

        return \sprintf(
            '((%s ->> %s)::numeric)',
            $this->field->dispatch($sqlWalker),
            $this->key->dispatch($sqlWalker),
        );
    }
}
