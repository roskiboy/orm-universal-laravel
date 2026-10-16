<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Function invocation. Unknown functions are compiled as-is so native database
 * functions remain accessible; capability checks only apply to known features.
 *
 * @api public
 */
class FunctionCall extends Expression
{
    /**
     * @param  list<Expression>  $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
        public readonly bool $distinct = false,
    ) {}

    /**
     * Scalar arguments are bound parameters, not nodes: they carry no
     * capability and are excluded from AST traversal.
     *
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return array_values(array_filter(
            $this->arguments,
            static fn (mixed $argument): bool => $argument instanceof AstNodeInterface,
        ));
    }

    public function over(?WindowExpression $window = null): WindowExpression
    {
        return new WindowExpression($this, $window);
    }
}
