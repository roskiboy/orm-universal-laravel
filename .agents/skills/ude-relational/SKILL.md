---
name: ude-relational
description: OrmUniversal relational engine (OrmUniversal\Relational) - AST, OO expressions, SQL compiler, versioned dialects, PDO, recordsets, hydration, transactions, bulk and portability analysis. Use when building SQL queries without Laravel, choosing/handling dialects, streaming large results, or inspecting portability/performance. Triggers: "OrmUniversal\Relational", "QueryBuilder", "SqliteDialect", "QueryExecutor", "PdoConnection", "recordset", "insertMany", "portability()".
---

# UDE Relational (OrmUniversal\Relational)

## When to use this package

- SQL queries without Laravel (CLI, workers, pure services).
- Selecting a dialect and real version (MySQL, MariaDB, PostgreSQL, SQLite, SQL Server, Oracle).
- Materialized or streaming recordsets for large datasets.
- Upsert/returning, transactions with savepoints, bulk insert/update/delete.
- Analyzing portability and static cost before executing.

Dependency: `OrmUniversal\Contracts`. Requires `ext-pdo` and `ext-json`.

## Key APIs (verified real names)

- Dialects: `Dialect\SqliteDialect::make('3.49.2')`, `PostgreSqlDialect::make('18.0')`,
  `MySqlDialect`, `MariaDbDialect`, `SqlServerDialect`, `OracleDialect`. All expose
  `capabilities()`, `supports()`, `limitations()`, `name()` (`sqlite-3.49.2`).
- Connection/execution: `Driver\PdoConnection`, `Execution\QueryExecutor`
  (`rows()`, `one()`, `value()`, `execute()`, `cursor()`, `transaction()`, `compile()`,
  `withParameters()`), `Driver\PdoTransaction` (`begin/commit/rollback/savepoint/rollbackTo/releaseSavepoint`).
- AST and builder: `Ast\SelectQuery`, `Ast\InsertQuery::into()->values()->returning()`,
  `Ast\UpdateQuery`, `Ast\DeleteQuery`, `Ast\Column::make()`, `Ast\Expression::param()`,
  `Builder\QueryBuilder::table($executor, 't')`.
- Fluent builder: `where/orWhere/whereIn/whereNull/whereBetween/whereExists/whereColumn`,
  `join/leftJoin/rightJoin`, `groupBy/having`, `orderBy/orderByDesc`, `limit/offset`,
  `get/first/value/pluck/count/exists/paginate`, `recordset/rawRecordset/cursor/lazy/chunk`,
  `insert/upsert/update/delete`, `insertMany/upsertMany/updateMany/deleteMany`,
  `compile/sql/bindings/fingerprint/requiredCapabilities`, `portability()/performance()/explain()`.
- Hydration: `Hydration\Hydrator::zero()|light()|entity($class)|factory($callable)`.
- Infra: `Execution\BatchPlanner` (placeholder budget per dialect),
  `Execution\DestructiveStatementPolicy` (`ALLOW/WARN/REQUIRE_CONFIRMATION/DENY`),
  `Optimizer\PortabilityAnalyzer`, `Optimizer\PerformanceAnalyzer`, `Observability\InMemoryProfiler`.

## Recommended patterns

### SQLite in-memory bootstrap (100% reproducible)

```php
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;

$connection = new PdoConnection(new \PDO('sqlite::memory:'), 'sqlite');
$executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

$executor->execute(new RawQuery(
    "create table orders (id integer primary key autoincrement, seller text not null, total numeric not null default 0, status text not null default 'pending')",
));

$orders = QueryBuilder::table($executor, 'orders');
$orders->insert([['seller' => 'ana', 'total' => 120, 'status' => 'paid']]);
$rows = $orders->where('status', 'paid')->orderByDesc('total')->get();
```

`QueryBuilder` is **mutable**: reuse it only when you want to accumulate conditions. For
independent queries create a new builder (or a factory closure).

### OO expressions and named parameters

```php
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Expression;

$query = $orders
    ->select('seller', 'total')
    ->where(Column::make('total')->gte(50))
    ->where(function ($group): void {
        $group->where('status', 'paid')->orWhere('status', 'pending');
    })
    ->using(['min' => 100]);
// Column::make('total')->gt(Expression::param('min')) for named placeholders
```

Values are **never** interpolated: the compiler always emits bindings.

### Recordsets and streaming

```php
$recordset = $orders->light()->recordset();   // stdClass
$recordset->count();
$recordset->first()->seller;

foreach ($orders->orderBy('id')->cursor() as $row) { /* real streaming */ }

$orders->orderBy('id')->chunk(500, function (array $chunk, int $page): bool {
    return true; // false stops
});
```

`cursor()`/`chunk()` consume the PDO cursor without re-executing; `get()` materializes. `insertMany`
neither hydrates nor fires events (documented): it receives raw rows.

### Upsert, returning and transactions

```php
use OrmUniversal\Relational\Ast\InsertQuery;

$orders->upsertMany([
    ['seller' => 'ana', 'total' => 130, 'status' => 'paid'],
], ['seller'], ['total', 'status']);

$inserted = $executor->rows(
    InsertQuery::into('orders')->values(['seller' => 'zoe', 'total' => 20])->returning(['id', 'seller']),
);

$tx = $executor->transaction();
$tx->begin();
$tx->savepoint('before_bulk');
$orders->insert([['seller' => 'temp', 'total' => 1]]);
$tx->rollbackTo('before_bulk');
$tx->releaseSavepoint('before_bulk');
$tx->commit();
```

`returning()` is only materialized if the dialect has `Capability::RETURNING`
(SQLite ≥ 3.35, PostgreSQL, MariaDB; MySQL 8.0 does not have it).

### Analysis before executing

```php
$report = $orders->select('seller')->where('status', 'paid')->portability();
$report->percent();                 // 100.0 for portable SQL
$report->classification()->value;   // portable | partially_portable | dialect_specific

$performance = $orders->performance(); // estimatedComplexity, hydrationCost, streamingAvailable
$plan = $orders->where('status', 'paid')->explain(); // only if supportsExplain
```

## Common mistakes

- `insert()` expects `array<int, array<string,mixed>>` (a list of rows), not a single row.
- Reusing the same `QueryBuilder` and being surprised by accumulated `where`s.
- `update()`/`delete()` without `where`: the default policy is `WARN` (logs only if
  you inject a logger); for destructive environments use an explicit `DestructiveStatementPolicy` and
  `confirmDestructive()`.
- Executing without resolving named parameters: `QueryException` "Missing value for named parameter".
- Assuming `explain()` is universal: it throws `UnsupportedFeatureException` if the dialect does not expose
  `Capability::EXPLAIN`.
- Expecting `RETURNING` on MySQL 8.0: it does not exist; the alternative is `lastInsertId()` + re-read.
- Closing `PdoConnection` (`close()`) and continuing to use it: `ConnectionException`.
- Assuming consecutive ids after an `upsert`: on SQLite the conflicting insert attempt
  consumes the AUTOINCREMENT sequence (ids with gaps). Capture the real ids before
  `updateMany`/`deleteMany`.

## Honest limits

- SQL Server and Oracle are implemented with golden tests, but their **real integration is
  pending on infrastructure**; they are not declared stable.
- SQLite is a single writer (no row locks, limited `ALTER TABLE`, 32766 variables).
- Version support is by documented cutoff; the engine never declares a capability without a
  cutoff.
- `PdoConnection` caches statements (128 by default) and does not emulate prepares by default.
- Benchmark figures depend on the engine/dataset; they are not universal promises.
