# Prompt: use OrmUniversal\Relational in an application

## Context

`OrmUniversal\Relational` runs real SQL through PDO, with versioned dialects,
a compiler with bindings, recordsets and portability analysis. It does not depend on Laravel.

## Goal

Ask an agent to:

1. Install `OrmUniversal\Relational` (`composer require roskiboy/orm-universal-laravel`).
2. Build the chain `PdoConnection` → `QueryExecutor($connection, $dialect)`.
3. Detect the dialect with `DialectResolver` or explicitly choose
   `PostgreSqlDialect::make($version)` / `MySqlDialect` / `MariaDbDialect` / `SqliteDialect`.
4. Write queries with `QueryBuilder::table($executor, 'tabla')` or with the AST
   (`SelectQuery::make(...)->select(...)->where(Column::make(...)->gte(...))`).
5. Use `insert/upsert/update/delete`, `insertMany/upsertMany/updateMany/deleteMany`.
6. Apply `recordset()/light()/cursor()/chunk()` to read without over-materializing.
7. Before executing, review `portability()` and `performance()`; for real plans,
   `explain()` only if `capabilities()->supports(Capability::EXPLAIN)`.

## Rules

- Values always through bindings; identifiers through the compiler. Raw SQL only via
  classified `RawExpression`/`SqlFragment`.
- For `UPDATE`/`DELETE` without `WHERE` in production: `DestructiveStatementPolicy::DENY` or
  `REQUIRE_CONFIRMATION` + `confirmDestructive()`.
- Each independent query uses a new `QueryBuilder` (it is mutable).
- `insert()` receives a list of rows (`[[...]]`); `insertMany()` neither hydrates nor fires events.
- Handle `PersistenceException` (QueryException, ConnectionException,
  UnsupportedFeatureException) and log context/alternatives.

## Deliverables

- Code that works without Laravel and without global connections.
- Tests: one golden compilation test and one integration test (SQLite `:memory:` works).
- No `vendor/bin/phpstan` errors.

## Verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Repo examples

`examples/basic.php` (SELECT/INSERT), `intermediate.php` (OO expressions),
`advanced.php` (streaming/recordsets), `expert.php` (upsert/returning/transactions/bulk),
`extreme.php` (portability, performance, batch planner and destructive policy).
