<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Subquery used as a scalar expression, a FROM source or an IN operand.
 *
 * @api public
 */
final class Subquery extends Expression
{
    public function __construct(
        public readonly SelectQuery $query,
    ) {}

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->query];
    }
}
