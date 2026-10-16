<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use Closure;
use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

/**
 * JOIN clause. Supports plain tables, subqueries and USING columns.
 * ON accepts an expression or a closure over a join predicate group.
 *
 * @api public
 */
final class Join extends Node
{
    /**
     * @param  list<string>  $using
     */
    public function __construct(
        public readonly JoinType $type,
        public readonly TableIdentifier|SelectQuery|string $table,
        public readonly ?string $alias = null,
        public readonly ?Expression $on = null,
        public readonly array $using = [],
        public readonly bool $straight = false,
    ) {}

    public function nodeType(): string
    {
        return 'Join';
    }

    public static function on(JoinType $type, TableIdentifier|SelectQuery|string $table, Expression|Closure|string $first, mixed $operator = null, mixed $value = null): self
    {
        if ($first instanceof Closure) {
            $group = new PredicateGroup;
            $first($group);

            return new self($type, $table, null, $group->toExpression());
        }

        if ($first instanceof Expression) {
            return new self($type, $table, null, $first);
        }

        // Raw string ON clause: explicit raw, developer supplied.
        return new self($type, $table, null, RawExpression::trusted($first));
    }

    public function using(string ...$columns): self
    {
        return new self($this->type, $this->table, $this->alias, $this->on, array_values($columns), $this->straight);
    }

    public function as(string $alias): self
    {
        return new self($this->type, $this->table, $alias, $this->on, $this->using, $this->straight);
    }

    public function straight(): self
    {
        return new self($this->type, $this->table, $this->alias, $this->on, $this->using, true);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        $children = [];
        if ($this->table instanceof SelectQuery) {
            $children[] = $this->table;
        }
        if ($this->on !== null) {
            $children[] = $this->on;
        }

        return $children;
    }
}
