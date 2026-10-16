# Contributing — ORM Universal Laravel

Thank you for contributing. Core rules: **PDO-first**, values always as bindings, and the
Core layers (`Contracts`, `Relational`, `NoSql`) never import `Illuminate\*`.

## Requirements

- PHP `^8.3`, `ext-pdo`, `ext-json`
- Composer 2.x
- Optional: `pdo_sqlite` (always for tests), `pdo_mysql`, `pdo_pgsql`, `pdo_sqlsrv`,
  `pdo_oci` for real integration; `ext-mongodb`/`ext-redis` for the NoSQL backends

## Branch flow

- `main`: always green and publishable.
- `feat/<description>`, `fix/<description>`, `docs/<description>`, `chore/<description>`.
- One PR per purpose; rebase onto `main` before opening.

## Conventional Commits

```text
feat(dialect): add PostgreSQL 18 capability cutoffs
fix(compiler): keep bindings ordered in nested predicates
perf(pdo): reuse prepared statements in statement cache
docs(recordset): clarify streaming buffer semantics
```

Breaking change:

```text
feat(ast)!: require explicit TableIdentifier in SelectQuery::make()

BREAKING CHANGE: SelectQuery::make(string) is replaced by SelectQuery::make(TableIdentifier).
Consumers of the fluent builder are unaffected.
```

## Mandatory gates

```bash
composer install
vendor/bin/phpunit          # Contracts + Relational + NoSql + Laravel
vendor/bin/phpstan analyse
vendor/bin/pint
```

All four must pass. The suite includes real SQLite integration (in-memory); MariaDB,
PostgreSQL, MongoDB and Redis are configured through the `UDE_*` variables in
`phpunit.xml.dist`.

## Package rules

- `declare(strict_types=1)` in all files; `final class` by default.
- Zero Laravel dependencies in `src/Contracts`, `src/Relational`, `src/NoSql`.
- **PDO-first**: all execution goes through the PDO layer (`PdoConnection`, `PdoStatement`,
  `PdoCursor`, `PdoTransaction`); do not introduce alternative clients.
- Values never interpolated: bindings. Identifiers quoted and validated. Raw SQL only
  via `SqlFragment` with an explicit type.
- Per-version capabilities with documented cutoffs; an unsupported feature throws
  `UnsupportedFeatureException` with alternatives.
- NoSQL: filters are operator documents and pipelines are native stages; do not convert
  NoSQL into SQL.
- The SQL/DDL golden tests are contract: a change in output must be justified.
- Laravel layer: macros are additive; Eloquent behavior, order and nulls are never altered.

## Pull requests

1. Problem and solution described.
2. Tests: unit, golden SQL/DDL and SQLite integration where applicable.
3. `CHANGELOG.md` updated under `Unreleased`.
4. Document new capabilities, cutoffs or limits in `docs/<layer>/`.

All contributions are accepted under the project's MIT license.
