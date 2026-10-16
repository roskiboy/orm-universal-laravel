# Releases

## Versioning

The package follows **Semantic Versioning** (`MAJOR.MINOR.PATCH`) and declares the
current version in `composer.json` (`"version": "0.1.0"`). While the major is
`0`, the public API may be adjusted in minors, but always with a changelog and
migration notes.

Criteria:

- **MAJOR**: breaking change in classes, signatures or emitted SQL that breaks
  compatibility.
- **MINOR**: new backward-compatible capabilities or APIs (new dialects,
  passes, types, methods).
- **PATCH**: fixes without API change or changed expected SQL.

## CHANGELOG

`CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
and groups by sections (`Added`, `Changed`, `Fixed`, `Removed`, `Security`) under
`## [Unreleased]` and each dated version:

```markdown
## [Unreleased]
### Added
- New documented capability.

## [0.1.0] - 2026-10-06
### Added
- ...
```

The `Unreleased` section is the source for preparing the next release.

## Commits

The repository uses **Conventional Commits** as its message convention:

```text
feat(dialect): support VECTOR in MySQL 9.x
fix(compiler): flatten nested OR groups
docs(relations): document RelationOptimizer
test(migration): golden DDL for Oracle 19c
chore(deps): update phpunit to ^12
```

Usual types: `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `chore`.
A breaking change is marked with `!` and `BREAKING CHANGE:` in the footer; it applies to
shared contracts with `OrmUniversal\Contracts`.

## Release workflow status

In this package `.github/workflows/` contains **only `ci.yml`** (push/PR to
`main`, PHP 8.3–8.5 matrix, PHPUnit + PHPStan). **There is no automated release
workflow**; the release is a manual procedure:

1. Verify CI is green: `vendor/bin/phpunit` and
   `vendor/bin/phpstan analyse` (see [Testing](testing.md)).
2. Move the entries from `## [Unreleased]` to a section
   `## [X.Y.Z] - YYYY-MM-DD`.
3. Update `"version": "X.Y.Z"` in `composer.json`.
4. Commit (`chore(release): X.Y.Z`) and annotated tag `vX.Y.Z`.
5. Publish on Packagist (or the configured registry) pointing to the tag.

No empty releases are created and the changelog of already published versions is
not rewritten. For API changes, document the migration path before
publishing the major.
