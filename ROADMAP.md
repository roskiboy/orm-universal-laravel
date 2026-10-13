# Roadmap — orm-universal/laravel

Living document. Intent, not a date commitment. Supported line: `0.4.x`.

## Current (0.4.x)

- `Fulltext` interception (MySQL/MariaDB/PostgreSQL) and optimize-10/11 shapes (dates,
  JSON, `Exists`/`whereHas`, row values, `NullSafeEquals`); macros and `dynamicWhere`
  verified on `ude-*` drivers.
- **`ude:parity:api`** + `Analysis\ParityAnalyzer` (optimize-9): Laravel 13 parity
  audit with parity/alias/na/deferred/gap classification and report in `storage/ude`.
  Current result: 0 gaps in `Query\Builder`/`Eloquent\Builder`/`Schema\Blueprint`.
- AST interception with multi-condition joins in the `ON` clause (row-by-row parity).
- Service provider with auto-discovery, merged/publishable config, and facade alias.
- `Ude` kernel with registries, contexts, profiler, optimizer, plugins, and DocumentStore.
- `LaravelConnectionAdapter` reusing the live PDO and the real server version.
- `LaravelFeatureRegistry` by reflection (no version checks).
- `LaravelCompatibilityLayer` with six modes and guards in `strict`.
- Additive Eloquent bridge: `HasUniversalQueries` trait, `universal()`/`recordset()`
  macros, model, builder, and migration adapters.
- 16 `ude:*` commands.
- Octane/queue resets on termination events.
- Hybrid mode verified in the monorepo (`tests/Feature/Ude/SellerUdeTest.php`).

## Next

- Expand command test coverage with stable JSON output.
- Document adoption recipes from `hybrid` to `optimized` with benchmark metrics.
- Harden detection and reporting of clauses not copied by
  `LaravelBuilderAdapter`.
- Go deeper into `ude:migrate:codemod`: more patterns and a better diff.

## Future

- `native` mode with a report of Laravel-only features not covered.
- Integration of relation contracts with the `relational` relation engine
  in an opt-in fashion.
- Opt-in query cache connected to the Laravel cache (`cache.store`).

## Experimental

- `ude:doctor --json` for CI consumption.
- Assisted codemod with a more granular per-file `--interactive`.
- Plugin registry exposed to applications to contribute adapters.

## Research

- Strategy for progressive Eloquent → UDE migration without downtime.
- Transaction semantics across the Laravel connection pool and Octane.
- Compatibility with future Laravel 13+ versions while keeping feature detection.
