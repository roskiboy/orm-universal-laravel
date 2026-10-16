# AGENTS.md — ORM Universal Laravel (`roskiboy/orm-universal-laravel`)

Guide for AI agents and automated contributors working on this package. Since
`optimize-14` the four historical packages (`contracts`, `relational`, `nosql`, `laravel`)
live in this single repository and Composer package.

## Minimal architecture

```text
src/
├── Contracts/   (28 root interfaces, Enums, Exceptions, Support, ValueObjects)
├── Relational/  (Ast, Builder, Compiler, Dialect, Driver, Execution, Hydration,
│                 Recordset, Relation, Migration, Schema, Optimizer, Context, Type,
│                 Business, Cache, Plugin, Observability, Support)
├── NoSql/       (DocumentStore, Collection, Document, Filter, Aggregation, Driver)
└── Laravel/     (ServiceProvider, Ude, Adapters, Compatibility, Concerns, Console,
                  Analysis, Facades, Auto, Connections)
config/          (orm-universal.php, publishable)
tests/           (Contracts, Relational, NoSql, Laravel — one PSR-4 root per layer)
benchmarks/      (relational, laravel)
docs/            (contracts, relational, nosql, laravel)
```

PSR-4 mapping (unchanged public namespaces):

```text
OrmUniversal\Contracts\  → src/Contracts/    OrmUniversal\Relational\ → src/Relational/
OrmUniversal\NoSql\      → src/NoSql/        OrmUniversal\Laravel\    → src/Laravel/
```

## Layer rules

1. **Contracts** — no engine implementations, no I/O, no connections. Allowed runtime
   dependencies: `php ^8.3`, `psr/log`, `psr/simple-cache`. Every stable surface carries
   `@api public`; internal surfaces `@internal`.
2. **Relational** — PDO-first: all execution goes through the existing PDO layer
   (`PdoConnection`, `PdoStatement`, `PdoCursor`, `PdoTransaction`, `ConnectionManager`).
   Values travel as bindings; identifiers are quoted/validated; raw SQL only via
   `SqlFragment` with `RawType`. An unsupported capability throws
   `UnsupportedFeatureException` with engine/version/feature/alternatives; never degrade
   silently. SQL/DDL golden tests are contract.
3. **NoSql** — NoSQL is not converted into SQL artificially: filters are operator
   documents, pipelines are native stages. Remote drivers are detected with `available()`
   and `class_exists`; backends are optional (`suggest`), never hard dependencies.
4. **Laravel** — do not break vanilla Eloquent: macros are additive, there are no global
   interceptors, unknown clauses are omitted (never guessed), models are never modified,
   feature detection by reflection (no version comparisons), and all accumulated state is
   cleared in `Ude::flush()` from the Octane/queue events.

## Cross-cutting rules

- **Importing `Illuminate\*` in `Contracts`, `Relational` or `NoSql` is forbidden**; the
  dependency exists only in `src/Laravel`. Any PR that violates this is rejected.
- `declare(strict_types=1)` in all files; `final class` by default; composition over
  inheritance; one class per file.
- Immutable value objects and Octane-safe contexts (`ContextScope` + explicit flush).
- Commands are conservative: dry-run by default; `--write` and `--force` explicit.
- Values are never interpolated; raw SQL is explicit and typed.
- Class and method names in English; package docs in English; console text in Spanish.

## Commands (acceptance gates)

```bash
composer install
vendor/bin/phpunit          # 4 suites: Contracts, Relational, NoSql, Laravel
vendor/bin/phpstan analyse  # level 5; documented exceptions in phpstan.neon.dist
vendor/bin/pint
```

All gates must pass without errors. CI runs PHP 8.3, 8.4 and 8.5. Real integration:
MariaDB/PostgreSQL via `UDE_MARIADB_*`/`UDE_PGSQL_*`, MongoDB/Redis via `UDE_MONGODB_*`/
`UDE_REDIS_*` (off by default).

## When modifying

1. Look for consumers across the other layers before changing a contract.
2. Add/update tests in the corresponding `tests/<Layer>/` suite; keep golden SQL/DDL green.
3. Update `CHANGELOG.md` under `Unreleased` and the layer docs in `docs/<layer>/`.
4. Incompatible changes require `!` + `BREAKING CHANGE` and a migration note.
5. The Laravel layer verifies that Eloquent remains intact (results, order, nulls).

## Do not touch

- Do not edit `LICENSE`, `.github/` or the release flow without explicit instruction.
- Do not modify `composer.json` except for a release or dependency task.
- Do not add runtime dependencies beyond the declared ones.
