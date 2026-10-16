<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\ComparisonOperator;

/**
 * Row value predicate: (a, b) = (?, ?) / (a, b) in (...). Portable on MySQL,
 * MariaDB, PostgreSQL and SQLite; SQL Server and Oracle restrict the operator
 * set (documented per dialect).
 *
 * @api public
 */
final class RowValues extends Expression
{
    /**
     * @param  list<Expression>  $columns
     * @param  list<Expression>  $values
     */
    public function __construct(
        public readonly array $columns,
        public readonly ComparisonOperator $operator,
        public readonly array $values,
    ) {}

    public function nodeType(): string
    {
        return 'RowValues';
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [...$this->columns, ...$this->values];
    }
}
