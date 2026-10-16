<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Explicit alias wrapper for expressions that need one in projections.
 *
 * @api public
 */
final class AliasedExpression extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        string $alias,
    ) {
        $this->alias = $alias;
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->expression];
    }
}
