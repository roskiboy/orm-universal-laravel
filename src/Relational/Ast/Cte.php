<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Common table expression (WITH [RECURSIVE] name AS (...)).
 *
 * @api public
 */
final class Cte extends Node
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly SelectQuery $query,
        public readonly bool $recursive = false,
        public readonly array $columns = [],
    ) {}

    public function nodeType(): string
    {
        return 'Cte';
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->query];
    }
}
