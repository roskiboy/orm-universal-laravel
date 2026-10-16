<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * EXISTS / NOT EXISTS subquery predicate.
 *
 * @api public
 */
final class Exists extends Expression
{
    public function __construct(
        public readonly SelectQuery $query,
        public readonly bool $negated = false,
    ) {}

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->query];
    }
}
