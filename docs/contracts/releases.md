# Releases — OrmUniversal\Contracts

## Versioning policy

- SemVer: `MAJOR.MINOR.PATCH`. Current version: `0.1.0`.
- In `0.x`, an incompatible change increments `MINOR` and must be marked with
  `BREAKING CHANGE` in the commit.
- The public API is everything annotated `@api public`. Internal `src/` may change without notice.
- Interfaces and enums are the most stable surface: changing them impacts
  `relational`, `nosql`, and `laravel`.

## Artifacts

| Artifact | Description |
|---|---|
| Git tag | `vX.Y.Z` in `roskiboy/orm-universal-contracts` |
| `CHANGELOG.md` | Keep a Changelog format |
| Docs | Snapshot per tag according to `DOCUMENTATION.md` |
| Composer | `OrmUniversal\Contracts` package |

## Release checklist

1. `composer validate` with no errors.
2. `vendor/bin/phpunit` green (PHP 8.3, 8.4, and 8.5 in CI).
3. `vendor/bin/phpstan analyse` with no errors (level 6).
4. `vendor/bin/pint` with no pending changes.
5. Update `CHANGELOG.md`: move `Unreleased` to the version and date.
6. Check that no `@internal` has accidentally become `@api public`.
7. Confirm that `composer.json` reflects the version.
8. Create an annotated tag `vX.Y.Z` and publish release notes with links to the changelog.
9. Freeze the documentation snapshot for the tag (see `DOCUMENTATION.md`).

## Release notes

Always include:

- Summary of added/changed/removed contracts.
- Migration guide if there is a `BREAKING CHANGE`.
- Compatibility matrix with `relational`, `nosql`, and `laravel`.

## Documentation versioning

Published docs correspond to the latest tag. If a change on `main` has not yet been
published, mark it as "unreleased" in the changelog; do not present it as stable.
