# Testing

## Commands

```bash
composer install
vendor/bin/phpunit          # includes real SQLite integration (in-memory)
vendor/bin/phpstan analyse  # level 5
```

## PHPUnit configuration

`phpunit.xml.dist` defines:

- `bootstrap="vendor/autoload.php"`, `cacheDirectory=".phpunit.cache"`.
- `failOnWarning="true"`, `failOnRisky="true"`,
  `beStrictAboutOutputDuringTests="true"`.
- A testsuite named `Relational` pointing to the `tests/` directory.
- `<source>` over `src/` for coverage metrics.
- Environment variables for optional integration:
  `UDE_MARIADB_HOST`, `UDE_MARIADB_PORT`, `UDE_MARIADB_DATABASE`,
  `UDE_MARIADB_USERNAME`, `UDE_MARIADB_PASSWORD`, `UDE_PGSQL_HOST`,
  `UDE_PGSQL_PORT`, `UDE_PGSQL_DATABASE`, `UDE_PGSQL_USERNAME`,
  `UDE_PGSQL_PASSWORD`.

## Test organization

```text
tests/
├── Dialect/       GoldenSqlTest.php         # SQL per dialect (no server)
├── Engine/        EngineExtensionsTest.php  # optimizer, types, relations, business
├── Integration/   SqliteEngineTest.php      # real in-memory SQLite
│                  BuilderApiExtensionsTest.php
└── Migration/     DdlGoldenTest.php         # DDL per dialect (no server)
```

### Dialect (golden SQL)

`GoldenSqlTest` pins the exact SQL for MySQL, MariaDB, PostgreSQL, SQLite,
SQL Server and Oracle: quoting, limit/offset, RETURNING vs OUTPUT, upsert per
engine, JSON, CTE, BETWEEN/IN/EXISTS, UNION, window functions and locks.
It also verifies that unsupported features throw
`UnsupportedFeatureException`.

### Engine (no server)

`EngineExtensionsTest` covers immutable contexts, business resolver and traces,
specification composition, relation registry, optimizer plans,
object/entity/factory recordsets, normalize/validate/prune pipeline, destructive
policy, `BatchPlanner`, portability, performance, types and hydration.

### Integration (real SQLite)

`SqliteEngineTest` (@group integration) opens
`new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-test')` and runs
inserts, selects, light/entity hydration, update/delete, upsert,
RETURNING, transaction with rollback, chunk and pagination.
`BuilderApiExtensionsTest` covers bulk operations, destructive policy,
inspection (`dialect()`, `capabilities()`, `ast()`, `portability()`,
`performance()`, `explain()`) and streaming.

### Migration (golden DDL)

`DdlGoldenTest` compiles `SchemaOperation::createTable()` with each dialect and
compares `identity`/`auto_increment`, FKs, indexes and the SQLite case that rejects
`add_foreign_key` on an existing table.

## PHPStan

`phpstan.neon.dist`:

```neon
parameters:
    level: 5
    paths:
        - src
    treatPhpDocTypesAsCertain: false
    ignoreErrors:
        - identifier: new.static
        - identifier: property.onlyWritten
        - identifier: function.impossibleType
```

The `ignoreErrors` are justified by design: recordset factories that
return late static bindings, `RelationGraph` keeping the path for
future inspection and the raw identifier guard validated at runtime.

## CI

`.github/workflows/ci.yml` runs on `ubuntu-latest` with a PHP 8.3, 8.4 and
8.5 matrix, extensions `pdo, pdo_sqlite, pdo_mysql, pdo_pgsql, mbstring`, and runs:

1. `composer install --prefer-dist --no-interaction --no-progress`
2. `vendor/bin/phpunit`
3. `vendor/bin/phpstan analyse --no-progress`

## Best practices when contributing

- Every new feature needs a SQL/DDL golden test and, if it touches execution,
  a test against real SQLite.
- Do not pin performance figures in tests; verify the shape of the plan, not timings.
- Prefer `assertSame` over exact SQL so quoting changes are
  visible.
- Respect the limits documented per dialect in [Dialects](dialects.md).
