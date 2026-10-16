# Query Engine

`OrmUniversal\Relational\Builder\QueryBuilder` is the fluent facade. It has no
SQL state of its own: it delegates each call to a `SelectQuery` (`query()`) or
creates the corresponding write AST. The object-oriented API (expressions)
and the fluent API **converge on the same AST**.

```php
use OrmUniversal\Relational\Builder\QueryBuilder;

$builder = QueryBuilder::table($executor, 'orders');
```

## Construction

- `select(Expression|string ...$columns)` — replaces the projection.
- `addSelect(...)` — adds columns.
- `distinct(bool $distinct = true)`.
- `join(string $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null)`.
- `leftJoin(...)`, `rightJoin(...)` — same signature as `join()`.
- `groupBy(Expression|string ...$columns)`.
- `having(Expression|Closure $expression)`.
- `orderBy(Ordering|string|Expression $column, ?string $direction = null)`.
- `orderByDesc(Ordering|string|Expression $column)`.
- `latest(string $column = 'created_at')`, `oldest(...)`.
- `limit(?int $limit)`, `take(int $limit)`.
- `offset(?int $offset)`, `skip(int $offset)`.
- `union(QueryBuilder|SelectQuery $query, bool $all = false)`.

`QueryBuilder` does **not** expose `unionAll()`; for `union all` use
`union($otra, true)` or `SelectQuery::unionAll()`. It also does not expose `with()` (CTE),
`lock()` or `crossJoin()`: those capabilities are in `SelectQuery` and you can pass
the AST directly to the executor. See [AST](ast.md).

## Predicates

- `where(Expression|Closure|string $column, mixed $operator = null, mixed $value = null)`
- `orWhere(...)` — same signature, OR combination.
- `whereIn(string $column, array $values)` / `whereNotIn(...)`.
- `whereNull(string $column)` / `whereNotNull(...)`.
- `whereBetween(string $column, mixed $low, mixed $high)`.
- `whereExists(QueryBuilder|SelectQuery $query, bool $negated = false)`.
- `whereColumn(string $first, string $operator, string $second)`.

Closures receive a `PredicateGroup` and are **not executed** until compilation:

```php
$builder->where(function ($group): void {
    $group->where('status', 'paid')->orWhere('status', 'refunded');
});
```

When the first argument is an `Expression`, the second is interpreted as an
operator or as a value (if null, equality is used). See
[Expressions](expressions.md).

## Grouping with `apply()`

`apply(object $specification)` calls `apply($this)` on a
`QuerySpecificationInterface` and returns the builder, without executing anything:

```php
use OrmUniversal\Relational\Business\AbstractSpecification;

$overdue = new class extends AbstractSpecification {
    public function apply(object $query): void
    {
        $query->where('due_date', '<', date('Y-m-d'));
    }
};

$builder->apply($overdue)->orderBy('due_date');
```

See [Business Logic](business-logic.md).

## Execution and reading

- `get(): array` — hydrated rows.
- `first(): mixed` — first row (adds `limit 1`).
- `value(string $column): mixed`.
- `pluck(string $column, ?string $key = null): array`.
- `count(): int` — direct `SELECT COUNT(*)` when there is no distinct/group/having/union; otherwise it wraps.
- `exists(): bool` — direct `SELECT EXISTS(...)` when the dialect declares `supportsExistsProjection`
  (MySQL/MariaDB/PostgreSQL/SQLite); the rest use `count(*) limit 1`.
- `defer(): DeferredResult` — defers the read to the end of the request; duplicate queries
  (same SQL and bindings) are resolved only once per batch (`DeferredBatch`).
- `recordset(): RecordsetInterface` — materialized with the active hydration.
- `rawRecordset(): RecordsetInterface` — raw rows.
- `cursor(): RecordsetInterface` — `StreamingRecordset` over the PDO cursor.
- `lazy(int $chunkSize = 1000): \Generator`.
- `chunk(int $size, callable $callback): bool` — stop with `false`.
- `paginate(int $perPage = 15, int $page = 1): PaginatorInterface`.

## Writes

- `insert(array $rows): int` — rows as `list<array<string,mixed>>`.
- `upsert(array $rows, array $uniqueBy, ?array $updateColumns = null): int`.
- `update(array $values): int`.
- `delete(): int`.
- `insertMany(array $rows)`, `upsertMany(...)`, `updateMany(array $values, array $ids, string $key = 'id')`,
  `deleteMany(array $ids, string $key = 'id')` — split by placeholder budget
  with `batchPlanner()`.

`update()` and `delete()` apply `DestructiveStatementPolicy` (by default
`WARN`). Use `usingPolicy()` and `confirmDestructive()` to change it. See
[Security](security.md).

## Hydration and context

- `hydrateWith(Hydrator $hydrator)`, `zeroHydration()`, `light()`,
  `entity(string $class)`, `models(callable $factory)`, `eloquent(callable)`.
- `using(array $parameters)` — values for named `Parameter`s.

## Inspection

- `compile(): CompiledQuery`, `sql(): string`, `bindings(): array`.
- `ast(): AstNodeInterface`, `dialect(): AbstractDialect`, `capabilities(): CapabilitySet`.
- `fingerprint(): QueryFingerprint`, `requiredCapabilities(): array`.
- `toPlan(): array`, `queryPlan(): array`.
- `portability(): PortabilityReport`, `performance(): PerformanceReport`.
- `explain(): array` — throws `UnsupportedFeatureException` if the dialect does not
  support `Capability::EXPLAIN`.

See [Optimizer](optimizer.md) and [Performance](performance.md).
