# Prompt: use OrmUniversal\Contracts in an application

## Context

`OrmUniversal\Contracts` is the framework-less Core: interfaces, enums, value objects, and
exceptions. It does not compile, connect, or hydrate. It is the stable boundary for application
code and libraries to talk about persistence without coupling to an engine or to Laravel.

## Goal

Ask an agent to:

1. Add `OrmUniversal\Contracts` as a dependency (`composer require roskiboy/orm-universal-laravel`).
2. Model backend capabilities with `CapabilitySet` and `DialectVersion` instead of loose booleans
   or version string comparisons.
3. Declare raw SQL with `SqlFragment` (`trusted`, `parameterized`, `identifier`, `value`).
4. Use `QueryFingerprint` for cache/profiling keys.
5. Throw `UnsupportedFeatureException::forFeature(...)` when negotiating a missing feature and
   always log `report()`.

## Constraints

- Importing `Illuminate\*` into code that lives in the Core is forbidden.
- `contracts` performs no I/O: if the agent needs to compile or connect, it must use
  `OrmUniversal\Relational` or `OrmUniversal\NoSql`.
- Do not invent interfaces or enums: use the 28 existing interfaces and 20 enums.
- `declare(strict_types=1)` in every new file.

## Suggested steps

1. `composer require roskiboy/orm-universal-laravel`
2. Detect the real engine and version (e.g. `PDO::ATTR_SERVER_VERSION`) and build
   `DialectVersion::parse('mariadb', $version)`.
3. Define the required capability set with `Capability::*` and negotiate it with `requireAll()`
   before compiling any query.
4. Wrap raw fragments with the correct `RawType`; no interpolated values.
5. Add unit tests covering: missing capability (exception + `alternatives`),
   equivalent fingerprint, and different fingerprint.

## Deliverables

- Code that depends only on `OrmUniversal\Contracts\*`.
- Tests with `vendor/bin/phpunit`.
- `vendor/bin/phpstan analyse` with no errors.

## Verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Repo examples

- `examples/basic.php`: `CapabilitySet` + `DialectVersion`.
- `examples/intermediate.php`: `SqlFragment`.
- `examples/advanced.php`: `QueryFingerprint`.
- `examples/expert.php`: `UnsupportedFeatureException::report()`.
- `examples/extreme.php`: combined negotiation.
