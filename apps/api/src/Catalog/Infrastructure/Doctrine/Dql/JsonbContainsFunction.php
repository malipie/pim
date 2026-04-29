<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Postgres `@>` JSONB containment as a DQL function.
 *
 * Usage: `JSONB_CONTAINS(o.attributesIndexed, :payload) = true`
 * where `:payload` is a JSON string (the right-hand operand for @>).
 *
 * `attributes_indexed` is JSONB with a partial GIN index (`#34`)
 * scoped per-tenant via the Doctrine TenantFilter — this function is
 * the DQL hook that lets API filters (`AttributeFilter` from #43)
 * speak the raw containment dialect without dropping to native SQL.
 */
final class JsonbContainsFunction extends FunctionNode
{
    private ?Node $left = null;
    private ?Node $right = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $left = $parser->ArithmeticPrimary();
        \assert($left instanceof Node);
        $this->left = $left;
        $parser->match(TokenType::T_COMMA);
        $right = $parser->ArithmeticPrimary();
        \assert($right instanceof Node);
        $this->right = $right;
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        \assert($this->left instanceof Node && $this->right instanceof Node);

        return \sprintf(
            '(%s @> %s::jsonb)',
            $this->left->dispatch($sqlWalker),
            $this->right->dispatch($sqlWalker),
        );
    }
}
