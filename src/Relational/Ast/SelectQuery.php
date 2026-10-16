<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use Closure;
use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\Enums\SortDirection;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\ValueObjects\Ordering;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Compiler\CapabilityAnalyzer;

/**
 * SELECT query AST. Both the Laravel style builder and the object oriented
 * expression API converge into this representation.
 *
 * @api public
 */
final class SelectQuery extends Node implements QueryObjectInterface
{
    private ?TableIdentifier $table = null;

    private ?string $tableAlias = null;

    private ?SelectQuery $fromSubquery = null;

    private ?RawExpression $fromRaw = null;

    private ?string $fromRawAlias = null;

    /** @var array{type: string, index: string}|null */
    private ?array $indexHint = null;

    /** @var list<Expression> */
    private array $columns = [];

    private bool $distinct = false;

    /** @var list<Join> */
    private array $joins = [];

    private ?Expression $where = null;

    /** @var list<Expression> */
    private array $groups = [];

    private ?Expression $having = null;

    /** @var list<Ordering> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @var list<array{query: SelectQuery, all: bool}> */
    private array $unions = [];

    /** @var list<Cte> */
    private array $ctes = [];

    private ?string $lock = null;

    public static function make(string|TableIdentifier|self|null $table = null, ?string $alias = null): self
    {
        $query = new self;
        if ($table !== null) {
            $query->from($table, $alias);
        }

        return $query;
    }

    public function from(string|TableIdentifier|SelectQuery $table, ?string $alias = null): self
    {
        $this->fromRaw = null;
        $this->fromRawAlias = null;
        $this->indexHint = null;

        if ($table instanceof SelectQuery) {
            $this->fromSubquery = $table;
            $this->table = null;
            $this->tableAlias = $alias;

            return $this;
        }

        $identifier = is_string($table) ? TableIdentifier::fromString($table) : $table;
        $this->table = $identifier;
        $this->fromSubquery = null;
        $this->tableAlias = $alias ?? $identifier->alias;

        return $this;
    }

    /**
     * Raw FROM source with explicit bindings and an optional alias.
     *
     * @param  list<mixed>  $bindings
     */
    public function fromRaw(string $sql, array $bindings = [], ?string $alias = null): self
    {
        $this->fromRaw = RawExpression::parameterized($sql, $bindings);
        $this->fromRawAlias = $alias;
        $this->table = null;
        $this->tableAlias = null;
        $this->fromSubquery = null;

        return $this;
    }

    /**
     * MySQL style index hint (use/force/ignore index).
     */
    public function indexHint(string $type, string $index): self
    {
        $this->indexHint = ['type' => $type, 'index' => $index];

        return $this;
    }

    public function select(Expression|string ...$columns): self
    {
        $this->columns = self::normalizeColumns($columns);

        return $this;
    }

    public function addSelect(Expression|string ...$columns): self
    {
        $this->columns = array_merge($this->columns, self::normalizeColumns($columns));

        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    public function join(string|TableIdentifier|SelectQuery $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addJoin(JoinType::Inner, $table, $first, $operator, $value);
    }

    public function leftJoin(string|TableIdentifier|SelectQuery $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addJoin(JoinType::Left, $table, $first, $operator, $value);
    }

    public function rightJoin(string|TableIdentifier|SelectQuery $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addJoin(JoinType::Right, $table, $first, $operator, $value);
    }

    public function crossJoin(string|TableIdentifier|SelectQuery $table): self
    {
        $this->joins[] = new Join(JoinType::Cross, is_string($table) ? TableIdentifier::fromString($table) : $table);

        return $this;
    }

    public function crossJoinSub(self $query, string $alias): self
    {
        $this->joins[] = new Join(JoinType::Cross, $query, $alias);

        return $this;
    }

    public function straightJoin(string|TableIdentifier|SelectQuery $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addJoin(JoinType::Inner, $table, $first, $operator, $value, true);
    }

    public function straightJoinSub(self $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addSubJoin(JoinType::Inner, $query, $alias, $first, $operator, $value, true);
    }

    public function joinSub(self $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addSubJoin(JoinType::Inner, $query, $alias, $first, $operator, $value);
    }

    public function leftJoinSub(self $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addSubJoin(JoinType::Left, $query, $alias, $first, $operator, $value);
    }

    public function rightJoinSub(self $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addSubJoin(JoinType::Right, $query, $alias, $first, $operator, $value);
    }

    public function joinLateral(self $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addSubJoin(JoinType::InnerLateral, $query, $alias, $first, $operator, $value);
    }

    public function leftJoinLateral(self $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        return $this->addSubJoin(JoinType::LeftLateral, $query, $alias, $first, $operator, $value);
    }

    public function where(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->addWhere(LogicalOperator::And, $column, $operator, $value);
    }

    public function orWhere(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->addWhere(LogicalOperator::Or, $column, $operator, $value);
    }

    /**
     * @param  list<mixed>|self  $values
     */
    public function whereIn(string|Expression $column, array|self $values): self
    {
        $expression = $column instanceof Expression ? $column : Column::make($column);

        return $this->where(new InList($expression, $values));
    }

    /**
     * @param  list<mixed>|self  $values
     */
    public function whereNotIn(string|Expression $column, array|self $values): self
    {
        $expression = $column instanceof Expression ? $column : Column::make($column);

        return $this->where(new InList($expression, $values, true));
    }

    public function whereNull(string $column): self
    {
        return $this->where(Column::make($column)->isNull());
    }

    public function whereNotNull(string $column): self
    {
        return $this->where(Column::make($column)->isNotNull());
    }

    public function whereBetween(string $column, mixed $low, mixed $high): self
    {
        return $this->where(Column::make($column)->between($low, $high));
    }

    public function whereNotBetween(string $column, mixed $low, mixed $high): self
    {
        return $this->where(Column::make($column)->notBetween($low, $high));
    }

    public function whereExists(self $query, bool $negated = false): self
    {
        return $this->where(new Exists($query, $negated));
    }

    public function whereNotExists(self $query): self
    {
        return $this->whereExists($query, true);
    }

    public function whereColumn(string $first, string $operator, string $second): self
    {
        return $this->where(Comparison::of(Column::make($first), $operator, Column::make($second)));
    }

    public function groupBy(Expression|string ...$columns): self
    {
        $this->groups = array_merge($this->groups, self::normalizeColumns($columns));

        return $this;
    }

    public function having(Expression|Closure $expression): self
    {
        $having = self::normalizeHavingExpression($expression);

        if ($having !== null) {
            $this->having = $having;
        }

        return $this;
    }

    public function orHaving(Expression|Closure $expression): self
    {
        $having = self::normalizeHavingExpression($expression);

        if ($having === null) {
            return $this;
        }

        $this->having = $this->having === null
            ? $having
            : new LogicalExpression(LogicalOperator::Or, [$this->having, $having]);

        return $this;
    }

    private static function normalizeHavingExpression(Expression|Closure $expression): ?Expression
    {
        if ($expression instanceof Closure) {
            $group = new PredicateGroup;
            $expression($group);

            return $group->toExpression();
        }

        return $expression;
    }

    public function orderBy(Ordering|string|Expression $column, ?string $direction = null): self
    {
        if ($column instanceof Ordering) {
            $this->orders[] = $column;

            return $this;
        }

        $expression = $column instanceof Expression ? $column : Column::fromPath($column);
        $this->orders[] = strtolower((string) ($direction ?? 'asc')) === 'desc' ? $expression->desc() : $expression->asc();

        return $this;
    }

    public function orderByDesc(Ordering|string|Expression $column): self
    {
        $expression = $column instanceof Expression ? $column : ($column instanceof Ordering ? $column->expression : Column::fromPath($column));
        $this->orders[] = $expression instanceof Expression ? $expression->desc() : new Ordering($expression, SortDirection::Desc);

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderByDesc($column);
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function union(self $query, bool $all = false): self
    {
        $this->unions[] = ['query' => $query, 'all' => $all];

        return $this;
    }

    public function unionAll(self $query): self
    {
        return $this->union($query, true);
    }

    /**
     * @param  list<string>  $columns
     */
    public function with(string $name, self $query, bool $recursive = false, array $columns = []): self
    {
        $this->ctes[] = new Cte($name, $query, $recursive, $columns);

        return $this;
    }

    public function lock(string $mode = 'for update'): self
    {
        $this->lock = $mode;

        return $this;
    }

    public function clearOrderings(): self
    {
        $this->orders = [];

        return $this;
    }

    public function replaceWhere(Expression $expression): self
    {
        $this->where = $expression;

        return $this;
    }

    public function replaceHaving(Expression $expression): self
    {
        $this->having = $expression;

        return $this;
    }

    public function clearLimitOffset(): self
    {
        $this->limit = null;
        $this->offset = null;

        return $this;
    }

    // --- Inspection -------------------------------------------------------

    public function toAst(): AstNodeInterface
    {
        return $this;
    }

    public function fingerprint(): QueryFingerprint
    {
        $structure = json_encode($this->structure(), JSON_THROW_ON_ERROR);

        return QueryFingerprint::fromSql($structure, count($this->columns), hash('sha256', $structure));
    }

    /**
     * @return list<string>
     */
    public function requiredCapabilities(): array
    {
        return CapabilityAnalyzer::forNode($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPlan(): array
    {
        return [
            'source' => $this->table?->qualifiedName() ?? ($this->fromSubquery !== null ? 'subquery' : null),
            'columns' => array_map(static fn (Expression $column): string => $column->nodeType(), $this->columns),
            'joins' => array_map(static fn (Join $join): string => $join->type->value, $this->joins),
            'filters' => $this->where?->nodeType(),
            'groups' => count($this->groups),
            'having' => $this->having?->nodeType(),
            'orders' => count($this->orders),
            'limit' => $this->limit,
            'offset' => $this->offset,
            'distinct' => $this->distinct,
            'ctes' => array_map(static fn (Cte $cte): string => $cte->name, $this->ctes),
            'unions' => count($this->unions),
            'lock' => $this->lock,
            'required_capabilities' => $this->requiredCapabilities(),
        ];
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        $children = [];
        if ($this->fromSubquery !== null) {
            $children[] = $this->fromSubquery;
        }
        foreach ($this->ctes as $cte) {
            $children[] = $cte;
        }
        foreach ($this->columns as $column) {
            $children[] = $column;
        }
        foreach ($this->joins as $join) {
            $children[] = $join;
        }
        if ($this->where !== null) {
            $children[] = $this->where;
        }
        foreach ($this->groups as $group) {
            $children[] = $group;
        }
        if ($this->having !== null) {
            $children[] = $this->having;
        }
        foreach ($this->unions as $union) {
            $children[] = $union['query'];
        }

        return $children;
    }

    // --- Accessors used by the compiler ----------------------------------

    public function table(): ?TableIdentifier
    {
        return $this->table;
    }

    public function tableAlias(): ?string
    {
        return $this->tableAlias;
    }

    public function fromSubquery(): ?self
    {
        return $this->fromSubquery;
    }

    public function fromRawSource(): ?RawExpression
    {
        return $this->fromRaw;
    }

    public function fromRawAlias(): ?string
    {
        return $this->fromRawAlias;
    }

    /**
     * @return array{type: string, index: string}|null
     */
    public function indexHintValue(): ?array
    {
        return $this->indexHint;
    }

    /** @return list<Expression> */
    public function columns(): array
    {
        return $this->columns;
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    /** @return list<Join> */
    public function joins(): array
    {
        return $this->joins;
    }

    public function whereExpression(): ?Expression
    {
        return $this->where;
    }

    /** @return list<Expression> */
    public function groups(): array
    {
        return $this->groups;
    }

    public function havingExpression(): ?Expression
    {
        return $this->having;
    }

    /** @return list<Ordering> */
    public function orders(): array
    {
        return $this->orders;
    }

    public function limitValue(): ?int
    {
        return $this->limit;
    }

    public function offsetValue(): ?int
    {
        return $this->offset;
    }

    /** @return list<array{query: SelectQuery, all: bool}> */
    public function unions(): array
    {
        return $this->unions;
    }

    /** @return list<Cte> */
    public function ctes(): array
    {
        return $this->ctes;
    }

    public function lockMode(): ?string
    {
        return $this->lock;
    }

    // --- Internals --------------------------------------------------------

    private function addJoin(JoinType $type, string|TableIdentifier|SelectQuery $table, Expression|Closure|string|null $first, ?string $operator, mixed $value, bool $straight = false): self
    {
        $identifier = is_string($table) ? TableIdentifier::fromString($table) : $table;
        $alias = $identifier instanceof TableIdentifier ? $identifier->alias : null;

        if ($first === null) {
            $this->joins[] = new Join($type, $identifier, $alias, null, [], $straight);

            return $this;
        }

        if ($first instanceof Closure) {
            $group = new PredicateGroup;
            $first($group);
            $this->joins[] = new Join($type, $identifier, $alias, $group->toExpression(), [], $straight);

            return $this;
        }

        if ($first instanceof Expression) {
            $this->joins[] = new Join($type, $identifier, $alias, $first, [], $straight);

            return $this;
        }

        // String first: either a raw ON clause (contains a space or an operator) or column comparison.
        if (str_contains($first, ' ') || str_contains($first, '=')) {
            $this->joins[] = new Join($type, $identifier, $alias, RawExpression::trusted($first), [], $straight);

            return $this;
        }

        $this->joins[] = new Join($type, $identifier, $alias, Comparison::of(Column::make($first), (string) $operator, Column::make((string) $value)), [], $straight);

        return $this;
    }

    private function addSubJoin(JoinType $type, self $query, string $alias, Expression|Closure|string|null $first, ?string $operator, mixed $value, bool $straight = false): self
    {
        $on = null;

        if ($first instanceof Closure) {
            $group = new PredicateGroup;
            $first($group);
            $on = $group->toExpression();
        } elseif ($first instanceof Expression) {
            $on = $first;
        } elseif (is_string($first)) {
            $on = str_contains($first, ' ') || str_contains($first, '=')
                ? RawExpression::trusted($first)
                : Comparison::of(Column::make($first), (string) $operator, Column::make((string) $value));
        }

        $this->joins[] = new Join($type, $query, $alias, $on, [], $straight);

        return $this;
    }

    private function addWhere(LogicalOperator $operator, Expression|Closure|string $column, mixed $op, mixed $value): self
    {
        $expression = null;

        if ($column instanceof Closure) {
            $group = new PredicateGroup;
            $column($group);
            $expression = $group->toExpression();
        } else {
            $expression = PredicateGroup::predicate($column, $op, $value);
        }

        if ($expression === null) {
            return $this;
        }

        $this->where = $this->where === null
            ? $expression
            : new LogicalExpression($operator, [$this->where, $expression]);

        return $this;
    }

    /**
     * @param  list<Expression|string>  $columns
     * @return list<Expression>
     */
    private static function normalizeColumns(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            $normalized[] = $column instanceof Expression
                ? $column
                : ($column === '*' ? RawExpression::trusted('*') : Column::fromPath($column));
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function structure(): array
    {
        return [
            'type' => 'select',
            'from' => $this->table?->qualifiedName() ?? ($this->fromSubquery !== null ? 'subquery' : ($this->fromRaw !== null ? 'raw:'.$this->fromRaw->fragment->sql : null)),
            'columns' => array_map(static fn (Expression $column): string => $column->nodeType(), $this->columns),
            'distinct' => $this->distinct,
            'joins' => array_map(static fn (Join $join): string => $join->type->value, $this->joins),
            'where' => $this->where?->nodeType(),
            'groups' => array_map(static fn (Expression $group): string => $group->nodeType(), $this->groups),
            'having' => $this->having?->nodeType(),
            'orders' => array_map(static fn (Ordering $order): string => $order->direction->value, $this->orders),
            'limit' => $this->limit,
            'offset' => $this->offset,
            'unions' => count($this->unions),
            'ctes' => array_map(static fn (Cte $cte): string => $cte->name, $this->ctes),
            'lock' => $this->lock,
            'index_hint' => $this->indexHint,
        ];
    }
}
