# Release Process — orm-universal/laravel

Operational checklist to publish a package version.

## 1. Preparation

- [ ] `main` green in CI (PHP 8.3, 8.4, 8.5).
- [ ] Clean `composer install`.
- [ ] `composer validate` with no errors.
- [ ] `vendor/bin/phpunit` green.
- [ ] `vendor/bin/phpstan analyse` with no errors (level 5).
- [ ] `vendor/bin/pint` with no pending changes.
- [ ] Clean `git status`.

## 2. Laravel compatibility

- [ ] Test against Laravel 12.x and 13.x (`illuminate/console|database|support` ranges).
- [ ] Review `LaravelFeatureRegistry`: any new feature detected with
  `class_exists`/reflection, without comparing versions.
- [ ] Confirm the provider works without Octane installed.

## 3. Contracts with the ecosystem

- [ ] `orm-universal/contracts|relational|nosql` dependencies on `^0.1`.
- [ ] If `DocumentDriverInterface` or `DocumentStore` changes, coordinate with `nosql`.
- [ ] If the relations API changes, coordinate with `relational`.

## 4. Changelog and documentation

- [ ] `CHANGELOG.md` with a reviewed `Unreleased` section.
- [ ] Move to `## [X.Y.Z] - YYYY-MM-DD`.
- [ ] `BREAKING CHANGE` with a migration guide if applicable.
- [ ] `docs/` reflects the real hybrid mode, commands, and adapters.
- [ ] Tag documentation snapshot (`DOCUMENTATION.md`).

## 5. Tag and publication

- [ ] `composer.json` at `X.Y.Z`.
- [ ] Commit `chore(release): vX.Y.Z`.
- [ ] Annotated tag `vX.Y.Z`.
- [ ] Push commit and tag.
- [ ] GitHub release with notes.
- [ ] Verify resolution in Composer.

## Rollback

Tags are not rewritten. A corrective `PATCH` is published and the withdrawal of the
defective version is noted in the release notes.
