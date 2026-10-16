<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * IN / NOT IN with literal values or a subquery.
 *
 * @api public
 */
final class InList extends Expression
{
    /**
     * @param  list<mixed>|SelectQuery  $values
     */
    public function __construct(
        public readonly Expression $expression,
        public readonly array|SelectQuery $values,
        public readonly bool $negated = false,
    ) {}

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        $children = [$this->expression];

        if ($this->values instanceof SelectQuery) {
            $children[] = $this->values;
        } else {
            foreach ($this->values as $value) {
                $children[] = Expression::normalizeOperand($value);
            }
        }

        return $children;
    }
}
