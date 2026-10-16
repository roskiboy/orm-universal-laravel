# Testing — OrmUniversal\Laravel

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

CI: PHP 8.3, 8.4, and 8.5. The workflow clones `contracts`, `relational`, and `nosql` as
path repositories and requires `DEPS_TOKEN` only while those repos are private.

## Configuration

`phpunit.xml.dist`:

- `Laravel` suite over `tests/`, bootstrap `vendor/autoload.php`.
- `failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`.

`phpstan.neon.dist`: level 5 over `src/`, with `class.notFound` ignored because Octane
and queue events are resolved with `class_exists`.

## Existing suite

| File | Coverage |
|---|---|
| `tests/AdaptersTest.php` | Adapters (model, builder, migration, connection) |
| `tests/CompatibilityTest.php` | Compatibility modes, guards, and guarantees |

## Monorepo integration

The README documents the hybrid parity test
`tests/Feature/Ude/SellerUdeTest.php` in the `tms` application: it verifies
Eloquent↔UDE parity, recordsets, writes, and explicit soft deletes. That test lives in
the monorepo, not in the package.

## When writing tests

- Use real Eloquent test models; do not simulate `Builder` with fragile mocks.
- Cover:
  - `LaravelModelAdapter::metadata()` and `relationDefinitions()` for each mapped
    relation and one unmapped relation (`MorphTo`) that must be omitted.
  - `LaravelBuilderAdapter` with known clauses and with an unknown one that is omitted.
  - `LaravelCompatibilityLayer` in `strict` (raw SQL and legacy API).
  - `LaravelFeatureRegistry` (boolean detection, no versions).
  - The `ude:*` commands with `artisan()` in test mode, without touching real data.
- Do not depend on optional extensions: the adapters must work with SQLite.
- Clear container state between tests (`Ude::flush()`).

## Acceptance criteria

`vendor/bin/phpunit` and `vendor/bin/phpstan analyse` green, `composer validate` with no
errors, and `vendor/bin/pint` with no pending changes.
