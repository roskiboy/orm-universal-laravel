<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\CompiledExpression;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;

/**
 * Compiles query objects and expressions into SQL + bindings.
 *
 * @api public
 */
interface CompilerInterface
{
    public function compileQuery(QueryObjectInterface $query): CompiledQuery;

    public function compileExpression(ExpressionInterface $expression): CompiledExpression;
}
