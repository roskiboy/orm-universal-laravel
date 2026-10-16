# AST

All nodes extend `OrmUniversal\Relational\Ast\Node`, which implements
`AstNodeInterface` with `nodeType()` (class name without reflection) and
`children()`. Queries additionally implement `QueryObjectInterface`
(`toAst()`, `fingerprint()`, `requiredCapabilities()`, `toPlan()`).

## SelectQuery

```php
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\SelectQuery;

$query = SelectQuery::make('orders', 'o')   // table + optional alias
    ->select('id', 'total')                 // '*' is compiled as a trusted RawExpression
    ->addSelect(Column::make('created_at')->as('date'))
    ->distinct()
    ->join('customers', 'customers.id', '=', 'o.customer_id')
    ->leftJoin('refunds', function ($group) {
        $group->whereColumn('refunds.order_id', '=', 'o.id')->whereNull('refunds.deleted_at');
    })
    ->where('total', '>=', 100)
    ->whereIn('status', ['paid', 'refunded'])
    ->groupBy('o.id')
    ->having(Column::make('total')->sum()->gt(500))
    ->orderByDesc('total')
    ->limit(50)
    ->offset(0);
```

Relevant methods: `from()`, `select()`, `addSelect()`, `distinct()`,
`join()`/`leftJoin()`/`rightJoin()`/`crossJoin()`, `where()`/`orWhere()`,
`whereIn()`/`whereNotIn()`, `whereNull()`/`whereNotNull()`, `whereBetween()`,
`whereExists()`, `whereColumn()`, `groupBy()`, `having()`, `orderBy()`,
`orderByDesc()`, `latest()`/`oldest()`, `limit()`/`take()`,
`offset()`/`skip()`, `union()`/`unionAll()`, `with()` (CTE),
`lock()`, and the mutation helpers `clearOrderings()`, `replaceWhere()`,
`replaceHaving()`, `clearLimitOffset()`.

Accessors used by the compiler: `table()`, `tableAlias()`,
`fromSubquery()`, `columns()`, `isDistinct()`, `joins()`, `whereExpression()`,
`groups()`, `havingExpression()`, `orders()`, `limitValue()`, `offsetValue()`,
`unions()`, `ctes()`, `lockMode()`.

CTE and UNION:

```php
$active = SelectQuery::make('users')->select('id')->where('active', true);

$report = SelectQuery::make('orders')
    ->with('active_users', $active, recursive: false, columns: [])
    ->select('id')
    ->unionAll(SelectQuery::make('archived_orders')->select('id'))
    ->orderBy('id');
```

The compiler rejects `ORDER BY`/`LIMIT` in members of a UNION; they must go in the
outer query (`CompilationException`).

## InsertQuery

```php
use OrmUniversal\Relational\Ast\InsertQuery;

$insert = InsertQuery::into('users')
    ->values(['name' => 'ana', 'age' => 30])
    ->rows([['name' => 'bob', 'age' => 25]])
    ->onConflict(['name'])
    ->upsert(['age'])          // null updates all non-key columns
    ->returning(['id', 'name']);

$fromSelect = InsertQuery::into('users_backup')->fromSelect(
    SelectQuery::make('users')->select('id', 'name'),
);
```

`ignore()` and `replace()` also exist. Every feature (`upsert`, `ignore`,
`replace`, `returning`) adds a required capability that the dialect negotiates.
Accessors: `table()`, `columns()`, `rowsData()`, `selectQuery()`,
`returningColumns()`, `isUpsert()`, `updateColumns()`, `isIgnore()`,
`isReplace()`, `conflictColumns()`.

## UpdateQuery

```php
use OrmUniversal\Relational\Ast\UpdateQuery;

$update = UpdateQuery::make('users')
    ->set(['age' => 31, 'active' => false])
    ->where('id', 1)
    ->orderBy('id')
    ->limit(1)
    ->returning(['id']);
```

`limit()` requires `Capability::UPDATE_LIMIT`; `returning()` requires
`Capability::RETURNING` or `OUTPUT_CLAUSE`.

## DeleteQuery

```php
use OrmUniversal\Relational\Ast\DeleteQuery;

$delete = DeleteQuery::from('sessions')->where('expired', true)->limit(100);
```

Without `where()`, the query is representable but the destructive policy decides
at execution time. See [Security](security.md).

## RawQuery

```php
use OrmUniversal\Relational\Ast\RawQuery;

$raw = new RawQuery('select count(*) from users where active = ?', [1]);
$executor->rows($raw);
```

Constructor: `(string $sql, array $bindings = [], array $capabilities = [], ?string $dialect = null, ?string $dialectVersion = null)`.
`compiled()` returns a ready `CompiledQuery`. Use it for DDL, `EXPLAIN` or
trusted internal queries.

## PredicateGroup and closures

`PredicateGroup` exists for the closures of `where()`/`having()`/`join()` and
for `Join::on()`. It supports `where()`, `orWhere()`, `whereIn()`, `whereNull()`,
`whereNotNull()`, `whereBetween()` and `toExpression()`. Closures are applied
at AST construction time, never during execution:

```php
$query->where(function ($group): void {
    $group->where('a', 1)->orWhere(function ($nested): void {
        $nested->where('b', 2)->whereNull('c');
    });
});
```

`PredicateGroup::predicate()` normalizes `['a', 'in', [1,2]]`, symbolic
operators (`=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `not like`) and omitted
values or operators. An unknown operator throws `InvalidArgumentException`.

## Auxiliary nodes

- `Join(JoinType $type, TableIdentifier|SelectQuery|string $table, ?string $alias, ?Expression $on, array $using)`.
- `Cte(string $name, SelectQuery $query, bool $recursive, array $columns)`.
- `Expression::normalizeOperand(mixed)` converts scalars into `Value` and
  `SqlFragment` into `RawExpression`.

See [Expressions](expressions.md), [Compilation](architecture.md) and
[Query Engine](query-engine.md).
