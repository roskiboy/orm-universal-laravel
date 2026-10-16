# Troubleshooting

Real package errors and their cause. The quoted messages come from the code.

## Connection and drivers

**`PDO driver [xxx] is not available. Installed drivers: ...`**
(`DriverException`). The PDO extension is missing. Install it and enable the
corresponding line in `php.ini` (`extension=pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`,
`pdo_sqlsrv`, `pdo_oci`). On Windows restart Laragon/the web server.

**`Unable to connect to [xxx] database [yyy]: ...`** (`DriverException`).
Credentials, host/port or a nonexistent database. Try first with
`PdoConnection::ping()` or check `ConnectionConfig::toDsn()`.

**`Connection [default] is closed.` / `The connection was closed.`**
(`ConnectionException`). A connection was used after `close()`. Reopen or
reconfigure the `ConnectionManager`; in long workers use `purge()` between
requests.

## Queries

**`Query failed on connection [x]: ...`** (`QueryException`). Engine error
(nonexistent table, constraint, syntax). The context includes
`['sql' => ..., 'driver' => ...]`; replicate the SQL with its bindings.

**`Missing value for named parameter [:x].`** (`QueryException`). Pass the value
with `QueryBuilder::using(['x' => ...])` or
`QueryExecutor::rows($query, ['x' => ...])`.

**`Unresolved named parameter [:x] for connection [y]...`** (`QueryException`).
The `CompiledQuery` reached the driver unresolved: do not execute `compile()`
directly against `PdoConnection` for named parameters; use
`QueryExecutor` (which calls `withParameters()`).

**`Destructive statement without WHERE clause blocked: [UPDATE x].`**
(`QueryException`). The policy is `DENY` or `REQUIRE_CONFIRMATION`.
Options: add `where()`, `confirmDestructive()` or `usingPolicy(ALLOW)`.
See [Security](security.md).

**`Unsafe SQL identifier rejected: [x].`** (`QueryException`). A
`RawExpression` of type `Identifier` does not match the strict regex. Use a
simple identifier `tabla` or `tabla.columna`, or pass it through `Column`.

## Compilation

**`SELECT requires a FROM source...` / `SELECT requires a FROM source (table or derived table).`**
(`CompilationException`). `SelectQuery::make()` without a table or `from()`. For
`select 1` use `RawQuery`.

**`A derived table requires an alias.`** `SelectQuery::make($subquery)` needs
the second alias argument. Same for subqueries in `join()`.

**`INSERT requires at least one row of values.`** Add `values()` or `rows()`
(or `fromSelect()`).

**`UPDATE requires at least one SET value.`** `set()` was left empty.

**`ORDER BY / LIMIT on a UNION member is not portable; apply them to the outer query.`**
Move `orderBy()`/`limit()` to the query containing the `union()`.

**`Empty logical expression group is not allowed.`** (`Validator`). A
`LogicalExpression` without parts; an empty group from `PredicateGroup::toExpression()`
returns `null` and is not added, so check the manual construction.

**`Unsupported expression node [x].` / `Unsupported query object [x].`**
A node outside the supported AST was compiled; check the contracts version.

## Unsupported capabilities

All of them throw `UnsupportedFeatureException` and its `report()` explains engine,
version, reason and alternatives:

- MySQL and `returning()` → use `lastInsertId()` or a later `select`.
- PostgreSQL and `delete()->limit()` → `DELETE` by PK subquery.
- SQL Server and `lock()` → `UPDLOCK` hints via raw SQL or optimistic concurrency.
- SQL Server and `STRING_AGG DISTINCT` / Oracle and `LISTAGG DISTINCT` →
  pre-aggregate in a subquery.
- MariaDB < 10.5 with `returning()` → upgrade or avoid the clause.

If the feature is indeed valid in your version, pass the correct version to the
dialect's `make($version)`.

## Hydration and results

**`Entity hydration requires a class name.` /
`Entity class [x] does not exist.`** (`HydrationException`). Check
`Hydrator::entity($class)` and the autoload.

**A `cursor()` does not allow `previous()`**. `PdoCursor` is forward-only; if
you need to go back, materialize with `get()` or `toArray()` before navigating.

**`explain()` throws `UnsupportedFeatureException`**. The dialect does not declare
`Capability::EXPLAIN`; run the native EXPLAIN via `RawQuery` or in the CLI.

## Business and relations

**`BusinessOperationException` for ambiguity**. The same scope exists in two
models: pass `$model` to `scope()`/`apply()` or rename the scope.

**`NOT is not expressible as a portable query specification...`**. Express the
positive condition instead of negating a complete specification.

**`Unknown relation [x] for [y].` / `Batch relation query requires at least one parent key.`**
(`RelationException`). Register the relation in `RelationRegistry` or do not call
`batch()` with an empty list.

## Migrations and schema

**`SQLite cannot add a foreign key to an existing table; recreate the table with the constraint.`**
(`MigrationException`). Recreate the table or move the FK into `create table`.

**`MySQL does not support partial indexes (index [x]).`** Remove `where` from the
`IndexDefinition` or emulate it with a functional/generated index.

**`No schema inspector registered for [x].` / `Dialect factory already registered...`**
(`DialectException`). Use `SchemaInspectorFactory::for()` with a supported
vendor or register the dialect with `override: true`.

## Quick diagnosis

```php
$builder->toPlan();          // structural summary + required capabilities
$builder->portability()->toArray();
$builder->performance()->toArray();
$profiler->summary();        // queries, total_ms, slowest_ms, repeated ones
```

See [Architecture](architecture.md), [Optimizer](optimizer.md) and
[Performance](performance.md).
