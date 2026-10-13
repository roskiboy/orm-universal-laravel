# Security — orm-universal/laravel

## Principles

1. **Eloquent is not modified.** There are no global interceptors that could change
   results, filters, or transactions.
2. **Optional `strict` mode** for teams that want errors on legacy APIs or unclassified
   raw SQL:
   ```php
   $layer->guardRawSql($sql, $explicit); // requires SqlFragment::trusted()/parameterized()
   $layer->guardLegacyApi('query');      // throws QueryException
   ```
3. **Values as bindings** throughout the underlying relational engine; the clauses copied
   by `LaravelBuilderAdapter` keep values as data, not as SQL.
4. **Configurable destructive statement policy**:
   `allow`, `warn`, `require_confirmation`, `deny` (`destructive_statements`). It is applied
   when running migrations via `LaravelMigrationAdapter`, which also presents a prior
   safety report.
5. **Conservative commands**: `ude:schema:generate` requires `--write`; `ude:migrate:codemod`
   is an implicit dry-run and never writes silently.

## Credentials and data

- The connection is taken from Laravel (`config('database.connections.*')`); the package
  does not store credentials of its own.
- NoSQL credentials live in `config/orm-universal.php` / `.env`
  (`UDE_MONGODB_URI`, `UDE_REDIS_*`). Do not include them in reports or logs.
- `ude:doctor` and `ude:map` may include connection/table names; review the output
  before sharing it.
- The profiler (`profiler.enabled`, `limit`) records query events; in sensitive
  environments, disable it or limit its retention.

## State in workers

The provider resets context and profiler on `RequestTerminated`, `TaskTerminated`,
`TickTerminated`, `JobProcessed`, and `JobFailed`. If you run the platform outside those
events (your own processes), call `Ude::flush()` explicitly.

## Relevant attack surface

- User input used in `where(Column::..., $value)`: correct (binding).
- User input in `SqlFragment::trusted()`: the application's responsibility.
- `regex` patterns in NoSQL: they are not sanitized.
- `ude:project:analyze` inspects the project, it does not execute user SQL.

## Scope

Failures in Laravel, PDO, MongoDB, or Redis are out of scope for this package.
Report integration-layer vulnerabilities through the private channel in `SECURITY.md`.
