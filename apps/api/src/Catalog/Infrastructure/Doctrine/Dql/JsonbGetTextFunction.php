<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Postgres `->>` JSONB key extraction as a DQL function.
 *
 * Usage: `JSONB_GET_TEXT(o.completeness, 'pct')` emits
 * `o.completeness ->> 'pct'`. The result is the JSON value cast to
 * text — callers add their own numeric/boolean cast when needed
 * (e.g. `JSONB_GET_TEXT(...)::numeric > 80`).
 *
 * Powers `CompletenessFilter` (#43) for the `?completeness[gt]=80`
 * range queries.
 */
final class JsonbGetTextFunction extends FunctionNode
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
            '(%s ->> %s)',
            $this->field->dispatch($sqlWalker),
            $this->key->dispatch($sqlWalker),
        );
    }
}
