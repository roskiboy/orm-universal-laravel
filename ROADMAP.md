# Roadmap — ORM Universal Laravel

Living document. Intent, not a commitment to dates. Supported line: `0.7.x` (unified
package; pre-unification history under `docs/<layer>/CHANGELOG.md`).

## Current (0.7.x)

- Single package and repository: `contracts` + `relational` + `nosql` + `laravel` under
  one `composer.json` (`replace` of the four legacy names), four test suites and one
  release flow.
- Contracts: 28 interfaces, capability/vendor/type enums, identifiers, `SqlFragment`,
  `DialectVersion`, `CapabilitySet`, `QueryFingerprint`, `CompiledQuery`, schema
  definitions, `ConnectionConfig`, `QueryProfileEvent`, `ParameterBinding`; exception
  hierarchy with `UnsupportedFeatureException::report()`; `ApiStatus`.
- Relational: AST (`SelectQuery`, `InsertQuery`, `UpdateQuery`, `DeleteQuery`,
  `RawQuery`) and 16 expression types; SQL compiler with quoted identifiers, bindings
  and value/identifier/raw separation; six versioned dialects (MySQL 8.0–26.x,
  MariaDB 10.6–13.x, PostgreSQL 14–18, SQLite 3.35–3.54, SQL Server 2019–2025,
  Oracle 19c–26ai); capability engine with `UnsupportedFeatureException`; PDO layer with
  statement cache, cursors, transactions/savepoints, multi-connection and profiler hooks;
  array/streaming recordsets; zero/light/entity/factory hydration; schema inspectors and
  diff without DDL; migrations; optimizer safe passes; relations with batch loader, graph
  and N+1 detector; business registry and specifications; types; Octane-safe contexts.
- NoSql: `DocumentStore`, `Collection`, `Document`, `Filter`, `Aggregation\Pipeline`;
  drivers InMemory (reference), MongoDB (verified 8.0.4) and Redis (verified 5.0.14).
- Laravel: service provider with auto-discovery; `Ude` kernel; connection/model/builder/
  migration adapters; `LaravelCompatibilityLayer` with six modes and guards in `strict`;
  additive Eloquent bridge (`HasUniversalQueries`, `universal()`/`recordset()` macros);
  `ude:*` commands including `ude:parity:api` (0 gaps in `Query\Builder`,
  `Eloquent\Builder`, `Schema\Blueprint`) and `ude:parity:data` (row-by-row validation);
  Octane/queue resets on termination events.

## Next

- Real SQL Server and Oracle integration (currently pending on infrastructure; not
  declared stable).
- Reduce the residual PHPStan findings documented in `phpstan.neon.dist`.
- Expand DDL golden tests for the six dialects and the Redis 6.2–8.x test matrix.
- Complete the documentation of each contract with implementation examples.
- Expand command test coverage with stable JSON output.

## Future

- Deeper portability analysis with automatic rewrite recommendations.
- Query and schema cache connected to the Laravel ecosystem on an opt-in basis.
- `native` mode with a report of Laravel-only features not covered.
- Contract compatibility matrix for third parties that want to contribute drivers.
- Observability of NoSQL operations aligned with the ecosystem profiler.

## Experimental

- New optimizer passes (projection, duplicates) with semantic validation.
- Assisted codemod with a more granular per-file `--interactive`.
- Plugin registry exposed to applications to contribute adapters.

## Research

- Cross-engine compatibility for complex queries (CTE, JSON, windows).
- Strategy for progressive Eloquent → UDE migration without downtime.
- Transaction semantics across the Laravel connection pool and Octane.
- Common change streams semantics across NoSQL backends (MongoDB only today).
- Formal verification that the optimizer passes do not alter semantics.
