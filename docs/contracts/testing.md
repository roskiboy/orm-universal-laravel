# Testing — OrmUniversal\Contracts

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Configuration

- `phpunit.xml.dist`: bootstrap `vendor/autoload.php`, suite `Contracts` over `tests/`,
  `failOnWarning`, `failOnRisky`, and `beStrictAboutOutputDuringTests` enabled.
- `phpstan.neon.dist`: level 6 over `src/`, `treatPhpDocTypesAsCertain: false`.

## Existing suite

| File | Coverage |
|---|---|
| `tests/ValueObjects/CapabilitySetTest.php` | Construction, merge/diff, `requireAll`, and exception |
| `tests/ValueObjects/DialectVersionTest.php` | Version parsing and comparison |
| `tests/ValueObjects/IdentifiersAndFragmentsTest.php` | `TableIdentifier`, `ColumnIdentifier`, `SqlFragment` |
| `tests/ValueObjects/QueryFingerprintTest.php` | Stable query identity |

There is no database integration: the package is a pure contract. Real integration
lives in `relational` (SQLite in-memory) and `nosql` (InMemory, MongoDB, and Redis).

## When writing tests

- Use PHPUnit (`phpunit/phpunit ^11 || ^12`); do not add test dependencies without
  justifying them in the PR.
- For new interfaces, test the associated value object/enum first.
- Verify the message stability of `UnsupportedFeatureException::report()`, because
  it is an observable contract.
- Avoid `sleep`, network I/O, and global state; the suite must be hermetic.

## What a change must cover

1. New or fixed behavior.
2. Backward compatibility of the public API (`@api public`).
3. Edge cases of `CapabilitySet` (missing capabilities, non-boolean values as
   limits such as `maxPlaceholders`).

## Acceptance criteria

- `vendor/bin/phpunit` green.
- `vendor/bin/phpstan analyse` with no errors.
- `composer validate` with no errors.
- `vendor/bin/pint` with no pending changes (if available).
