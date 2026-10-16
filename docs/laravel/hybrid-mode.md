# Hybrid mode — OrmUniversal\Laravel

Hybrid mode (default) allows using vanilla Eloquent and the universal engine in the same
application, without either altering the other.

## The six modes

`OrmUniversal\Contracts\Enums\CompatibilityMode`:

| Mode | Value | Meaning |
|---|---|---|
| Legacy | `legacy` | Laravel-only behavior; the platform available passively |
| Compatibility | `compatibility` | Adapters active; byte-compatible results with Laravel |
| Hybrid | `hybrid` | Vanilla Eloquent + UDE queries/recordsets in parallel |
| Optimized | `optimized` | Platform optimizations with compatibility guarantees |
| Native | `native` | Platform first; Laravel-only features reported |
| Strict | `strict` | Legacy APIs and ambiguous magic throw an exception |

`allowsLegacyApis()` is `true` in all modes except `strict`.

## Configuration

```dotenv
UDE_COMPATIBILITY_MODE=hybrid
```

```php
$kernel = app(\OrmUniversal\Laravel\Ude::class);
$kernel->compatibility()->mode(); // CompatibilityMode::Hybrid
```

`LaravelCompatibilityLayer` allows changing the mode at runtime (`use()`).

## Declared guarantees

`report()` returns the invariants of the active mode:

- `eloquent_results_unchanged`
- `ordering_unchanged`
- `null_semantics_unchanged`
- `transaction_semantics_unchanged`
- `casting_unchanged`
- `events_unchanged`

Hybrid mode does not intercept Eloquent queries: these are non-intervention guarantees.

## `strict` guards

```php
$layer->guardRawSql($sql, $explicit); // requires SqlFragment::trusted()/parameterized()
$layer->guardLegacyApi('query');      // rejects legacy API
```

In `strict`, raw SQL without explicit classification throws `QueryException`.

## Connections

`LaravelConnectionAdapter` reuses the live Laravel PDO:

- `executor($connection, $profiler)` returns a `QueryExecutor` cached per connection.
- `dialectFor()` resolves the dialect with the real server version (SQLite queries
  `select sqlite_version()`).
- `summary()` exposes `name`, `driver`, `database`, and `server_version`.
- `flush()` resets cached executors and connections.

## Octane and workers

The provider registers `Ude::flush()` on `RequestTerminated`, `TaskTerminated`,
`TickTerminated`, `JobProcessed`, and `JobFailed`. `flush()` resets:

- `ContextScope` (immutable request contexts).
- `InMemoryProfiler`.

There is no mutable global state outside the kernel; in long-lived workers the flush is
the isolation boundary.

## AST fast path (opt-in interception)

The `ude-*` drivers (`ude-sqlite`, `ude-mysql`, `ude-mariadb`, `ude-pgsql`, `ude-sqlsrv`)
resolve to the native Laravel connection with their own query builder that adds an AST
fast path, enabled with:

```dotenv
UDE_AUTO=intercept
# or explicitly:
UDE_AUTO_INTERCEPT=true
# absolute kill switch:
UDE_AUTO_INTERCEPT=false
# config/orm-universal.php
'auto' => ['mode' => 'intercept', 'intercept' => null],
```

Fast-path safety rules:

- Only **whitelisted** shapes are optimized (simple table from, string/`*` columns,
  Basic/In/Null/NotNull/Between/Column/Nested wheres, joins of a single column
  comparison, orders, Basic groupBy/having, limit/offset, simple aggregates). Any other
  shape delegates to `parent::` without altering results.
- SQL is compiled with the UDE compiler but executed on the **same Laravel connection**
  (same PDO, transaction, `useWritePdo`, and fetch mode): `QueryExecuted`, `DB::listen`,
  the automatic monitor, and model events are preserved.
- Eloquent hydrates the same way (`stdClass` rows), including casts, accessors, and
  explicit soft deletes.
- Connections with a native driver are never intercepted, and `UDE_AUTO_INTERCEPT=false`
  disables the fast path even with `mode=intercept`.
- `ude-oracle` remains passthrough: Laravel does not include an Oracle connection; it
  requires an OCI8 resolver in the application.

Verified row by row against vanilla on SQLite and MariaDB (includes decimals as strings,
nulls, joins, aggregates, and keyset pagination). SQL Server integration implemented at
the compiler/connection level, pending infrastructure.

## Suggested progressive adoption

1. `legacy`/`compatibility`: measure with `ude:project:analyze` and `ude:map`.
2. `hybrid`: introduce `universal()`/`recordset()` in specific reads.
3. `optimized`: enable the opt-in cache and analyze portability/performance.
4. `native`/`strict`: only once there are no dependencies on legacy behaviors.
