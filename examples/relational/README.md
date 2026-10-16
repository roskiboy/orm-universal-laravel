# Runnable examples: OrmUniversal\Relational

All examples run against **real in-memory SQLite** via PDO, with no external servers.

## Requirements

- PHP 8.3+ with `ext-pdo` and `ext-pdo_sqlite`.
- Autoload: each file uses the package's `vendor/` if it exists and, if not, falls back to
  the monorepo root's `vendor/autoload.php`:

```php
$packageAutoload = __DIR__.'/../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;
```

```bash
# Option A: package dependencies
composer install
# Option B: tms monorepo (root vendor already contains OrmUniversal\Relational)
```

## Running

```bash
php examples/run.php     # runs all 5 and reports (exit 0 if all pass)
php examples/basic.php   # individual; exit 0 success, exit 1 failure
```

## Files

| File | Content |
|---|---|
| `basic.php` | `PdoConnection` + `SqliteDialect::make('3.49.2')` + `QueryExecutor`; raw DDL, INSERT/SELECT, count/exists, update/delete. |
| `intermediate.php` | OO expressions (`Column::make()->gte()`, groups, `in/between/like/isNull`), joins, `groupBy/having`, named parameters. |
| `advanced.php` | zero/light/entity hydration, `recordset()`, `cursor()` streaming, `chunk()`, `lazy()`, `paginate()`. |
| `expert.php` | `upsert/upsertMany`, `returning()`, transaction with savepoint, `insertMany/updateMany/deleteMany`, destructive policy. |
| `extreme.php` | `portability()`, `performance()`, `capabilities()`, `explain()`, fingerprint, `BatchPlanner` and `limitations()`. |

## API notes (real behavior)

- `QueryBuilder` is mutable: each example creates new builders per query.
- `insert()` receives a **list of rows**; `insertMany()` neither hydrates nor fires events.
- `returning()` requires `Capability::RETURNING` (SQLite ≥ 3.35, PostgreSQL, MariaDB;
  MySQL 8.0 does not support it).
- `update()`/`delete()` without `WHERE` go through `DestructiveStatementPolicy`.
- `explain()` only exists if the dialect declares `Capability::EXPLAIN`; SQLite supports it.
