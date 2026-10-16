<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\LogicalOperator;

/**
 * AND / OR / NOT / XOR group. Order is always preserved.
 *
 * @api public
 */
final class LogicalExpression extends Expression
{
    /**
     * @param  list<Expression>  $expressions
     */
    public function __construct(
        public readonly LogicalOperator $operator,
        public readonly array $expressions,
    ) {}

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return array_values($this->expressions);
    }

    /**
     * @return list<Expression>
     */
    public function parts(): array
    {
        return $this->expressions;
    }
}
