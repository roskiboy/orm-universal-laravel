# Releases — OrmUniversal\NoSql

## Versioning policy

- SemVer `MAJOR.MINOR.PATCH`. Current version: `0.1.0`.
- In `0.x`, an incompatible change increments `MINOR` and requires `BREAKING CHANGE`.
- The public API is `DocumentStore`, `Collection`, `Document`, `Filter`,
  `Aggregation\Pipeline`, `Driver\DocumentDriverInterface`, and the delivered drivers.
- Changing the semantics of a driver (for example, no longer filtering on the server) is a
  behavior change that must be documented even if it does not break signatures.

## Artifacts

| Artifact | Description |
|---|---|
| Git tag | `vX.Y.Z` in `roskiboy/orm-universal-nosql` |
| `CHANGELOG.md` | Keep a Changelog |
| Docs | Snapshot per tag (`DOCUMENTATION.md`) |
| Composer | `OrmUniversal\NoSql` |

## Release checklist

1. `composer validate` with no errors.
2. `vendor/bin/phpunit` (InMemory always; Mongo/Redis integration if servers are available).
3. `vendor/bin/phpstan analyse` with no errors (level 5).
4. `vendor/bin/pint` with no pending changes.
5. Review the capability table of each driver: it must reflect what has been tested.
6. Update `CHANGELOG.md` (move `Unreleased` to `X.Y.Z` with a date).
7. Verify the integration matrix: InMemory, MongoDB 8.0.4 (ext 2.4.0), and
   Redis 5.0.14 if the environment is available.
8. Create an annotated tag `vX.Y.Z` and release notes.
9. Freeze the `docs/` snapshot for the tag.

## Release notes

Include: new capabilities per driver, semantics changes, known limitations
(Redis aggregation, Atlas-only Vector Search), and a migration guide if applicable.

## Server versions

- MongoDB: integration verified against 8.0.4; the `mongodb/mongodb ^2.4` library.
- Redis: verified against 5.0.14 (EOL). The 6.2–8.x lines remain documented as
  supported by declared capabilities, but not tested in this repository.

Do not declare a combination "stable" if it is not covered by tests.
