# Compatibility

This document reflects the real state of the `OrmUniversal\Relational`
`0.1.0` package. No integrations are declared that do not exist.

## PHP and extensions

| Requirement | Status |
|---|---|
| PHP `^8.3` | CI tests 8.3, 8.4 and 8.5 |
| `ext-pdo` | mandatory |
| `ext-json` | mandatory |
| `ext-pdo_sqlite` | real integration verified |
| `ext-pdo_mysql` | golden SQL + capabilities; no local integration in this repo |
| `ext-pdo_pgsql` | golden SQL + capabilities; no local integration in this repo |
| `ext-pdo_sqlsrv` | implemented; integration pending on infrastructure |
| `ext-pdo_oci` | implemented; integration pending on infrastructure |

## Operating system

The library is pure PHP over PDO, with no native extensions of its own. CI runs
on `ubuntu-latest`; local development for this repository is Windows with
Laragon and in-memory SQLite. There is no OS-dependent code.

## Laravel

| Aspect | Status |
|---|---|
| Laravel dependency | **No**. The core does not import `Illuminate\*` |
| Adapters | `OrmUniversal\Laravel` package (outside this repo) |
| Eloquent | integrated via `Hydrator::factory()` / `EloquentRecordset::fromRows()` |
| Hybrid mode | documented in [Migration from Laravel](migration-from-laravel.md) |

## Engine matrix

| Engine | Lines | SQL compilation | DDL | Real local integration |
|---|---|---|---|---|
| SQLite | 3.35–3.54 | golden tests | golden tests | **yes**, in-memory (`SqliteEngineTest`) |
| MySQL | 8.0–26.x | golden tests | golden tests | not in this repo |
| MariaDB | 10.6–13.x | golden tests | golden tests | not in this repo |
| PostgreSQL | 14–18 | golden tests | golden tests | not in this repo |
| SQL Server | 2019–2025 | golden tests | golden tests | pending (README) |
| Oracle | 19c–26ai | golden tests | golden tests | pending (README) |

`phpunit.xml.dist` declares `UDE_MARIADB_*` and `UDE_PGSQL_*` variables for
integration runs in environments that have those servers.

## Honest status according to the README

> `0.1.0` · SQLite and golden tests green. SQL Server/Oracle implemented with
> integration pending on infrastructure (not declared stable).

Practical interpretation:

- **Verified**: multi-dialect compilation, per-version capabilities,
  DDL migrations and the full engine against real SQLite.
- **Implemented, without end-to-end verification in this repo**: SQL Server and
  Oracle. The API exists and compiles SQL/DDL, but there is no server in the local CI.
- **Ecosystem**: integration with MariaDB, PostgreSQL, MongoDB and Redis lives
  in the monorepo (see `docs/ude/`).

## Versioning and semantics

- SemVer and changelog in [Releases](releases.md).
- Negotiated capabilities: an unsupported feature throws
  `UnsupportedFeatureException` with alternatives, never silently
  incorrect SQL. See [Dialects](dialects.md).
- Immutable contexts and explicit `flush()` make the package safe for
  Octane/RoadRunner ([Octane migration](migration/octane.md)).
