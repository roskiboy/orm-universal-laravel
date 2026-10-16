# Release Process — ORM Universal Laravel

Operational checklist for publishing a version of `roskiboy/orm-universal-laravel`.

## 1. Preparation

- [ ] `main` green in CI (PHP 8.3, 8.4, 8.5; four suites).
- [ ] Clean `composer install`.
- [ ] `composer validate` without errors.
- [ ] `vendor/bin/phpunit` green, including in-memory SQLite integration.
- [ ] `vendor/bin/phpstan analyse` without errors (level 5; residual findings documented
  in `phpstan.neon.dist` must remain justified).
- [ ] `vendor/bin/pint` with no pending changes.
- [ ] Clean `git status`.

## 2. Engines and capabilities

- [ ] SQL and DDL golden tests green for MySQL/MariaDB, PostgreSQL, SQLite,
  SQL Server and Oracle.
- [ ] Version cutoffs reviewed in `Relational/Dialect/Capabilities/*`.
- [ ] Each dialect's `limitations()` reflects the real state.
- [ ] SQL Server/Oracle: do not declare "stable" without real integration executed.
- [ ] NoSQL drivers: `CapabilitySet` per driver matches the tested backends.

## 3. Real integration (if the environment is available)

- [ ] MariaDB (`UDE_MARIADB_*`), PostgreSQL (`UDE_PGSQL_*`), MongoDB (`UDE_MONGODB_*`)
  and Redis (`UDE_REDIS_*`) from `phpunit.xml.dist`.
- [ ] Record the exact server versions used in the verification.
- [ ] Optional: Laravel layer parity in a host app (`php artisan ude:parity:api`,
  `ude:parity:data`).

## 4. Changelog and documentation

- [ ] `CHANGELOG.md` with `Unreleased` reviewed.
- [ ] Move to `## [X.Y.Z] - YYYY-MM-DD`.
- [ ] `BREAKING CHANGE` with a migration guide if applicable.
- [ ] README and release notes consistent with the declared capabilities.

## 5. Tag and publication

- [ ] Commit `chore(release): vX.Y.Z`.
- [ ] Annotated tag `vX.Y.Z`.
- [ ] Push commit and tag.
- [ ] GitHub release with notes (`php tools/release.php --notes`).
- [ ] Verify resolution in Composer (`roskiboy/orm-universal-laravel:X.Y.Z`).

## deprecated names

The package declares `replace` for `orm-universal/contracts`, `orm-universal/relational`,
`orm-universal/nosql` and `orm-universal/laravel`: migrating projects only change the
`require` line. Do not tag the old repositories anymore.

## Rollback

Tags are not rewritten: a corrective `PATCH` is published and the withdrawal of the
defective version is noted.
