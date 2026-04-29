<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Dql;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Postgres ltree `<@` containment: `LTREE_DESCENDANT_OF(path, root)` is
 * true when `path` is `root` or any descendant.
 *
 * Powers `CategoryFilter` (#43): `?category=electronics.audio` resolves
 * to the category's path, then this function filters every catalog
 * object whose `path` lives at-or-below that root. The partial
 * GIST index on `objects.path WHERE kind='category'` (#33) answers
 * the containment in sub-millisecond on the demo dataset.
 *
 * Both arguments are explicitly cast to `ltree` so a varchar-side
 * value (parameter literal) is accepted alongside the column type.
 */
final class LtreeDescendantOfFunction extends FunctionNode
{
    private ?Node $path = null;
    private ?Node $root = null;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $path = $parser->ArithmeticPrimary();
        \assert($path instanceof Node);
        $this->path = $path;
        $parser->match(TokenType::T_COMMA);
        $root = $parser->ArithmeticPrimary();
        \assert($root instanceof Node);
        $this->root = $root;
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        \assert($this->path instanceof Node && $this->root instanceof Node);

        return \sprintf(
            '(%s::ltree <@ %s::ltree)',
            $this->path->dispatch($sqlWalker),
            $this->root->dispatch($sqlWalker),
        );
    }
}
