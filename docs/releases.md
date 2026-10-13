# Releases — orm-universal/laravel

## Versioning policy

- SemVer `MAJOR.MINOR.PATCH`. Current version: `0.1.0`.
- On `0.x`, a breaking change increments `MINOR` and requires `BREAKING CHANGE`.
- The public API is `OrmUniversalServiceProvider`, `Ude`, the facade, the adapters, the
  `HasUniversalQueries` trait, the `universal()`/`recordset()` macros, and the `ude:*`
  commands (signature and purpose).
- Changing hybrid-mode guarantees is a contract change: document it with the same
  severity as a signature change.

## Artifacts

| Artifact | Description |
|---|---|
| Git tag | `vX.Y.Z` on `roskiboy/orm-universal-laravel` |
| `CHANGELOG.md` | Keep a Changelog |
| Docs | Snapshot per tag (`DOCUMENTATION.md`) |
| Composer | `orm-universal/laravel` |

## Release checklist

1. `composer validate` with no errors.
2. `vendor/bin/phpunit` green.
3. `vendor/bin/phpstan analyse` with no errors (level 5).
4. `vendor/bin/pint` with no pending changes.
5. Verify compatibility with Laravel 12.x and 13.x (`illuminate/*` range).
6. Review that the 16 `ude:*` commands are still registered and with a coherent signature.
7. Update `CHANGELOG.md` (move `Unreleased` to `X.Y.Z` with a date).
8. Confirm `contracts`, `relational`, and `nosql` dependencies on `^0.1`.
9. Create annotated tag `vX.Y.Z` and release notes.
10. Freeze the `docs/` snapshot for the tag.

## Release notes

Include: compatibility modes, changes in adapters and macros, changes in commands,
Eloquent guarantees, and a migration guide if there is a `BREAKING CHANGE`.

## Rollback

Tags are not rewritten: a corrective `PATCH` is published and the withdrawal of the
defective version is noted.
