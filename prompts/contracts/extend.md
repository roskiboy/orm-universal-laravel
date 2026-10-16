# Prompt: extend OrmUniversal\Contracts

## Context

`contracts` is a stable public API (`0.1.0`, SemVer). Extending it does not mean modifying its
interfaces, but contributing new vocabulary without breaking consumers (relational, nosql, laravel).

## Goal

Ask an agent to choose the right extension:

1. **New capability**: use a free string in `CapabilitySet` (e.g. `'supportsVectorIndex'`)
   and, if it must be shared, add a constant in `OrmUniversal\Contracts\Support\Capability`.
   `CapabilitySet` accepts arbitrary keys by design.
2. **New value object**: `final`, immutable (`readonly`) class, with static factories
   (`of()`, `fromSql()`, `parse()`) and validation in the constructor. Implement `\Stringable`
   when its textual representation matters (pattern: `DialectVersion`, `QueryFingerprint`).
3. **New exception**: derive from `PersistenceException` to avoid breaking the capture
   contract; add static factories and context data via `withContext()`.
4. **New enum**: backed `string` enum if it serializes (pattern: `RawType`, `DialectVendor`),
   pure if it represents internal behavior (pattern: `CompatibilityMode` with methods).
5. **New interface**: add it at the root of `src/`, with `@api public` phpdoc and full types.

## Rules

- `declare(strict_types=1)` and types on properties/parameters.
- Zero new runtime dependencies without justification (allowed: `psr/log`, `psr/simple-cache`).
- Do not modify existing interfaces incompatibly: such a change requires a major version and a migration
  note in `CHANGELOG.md`.
- `Illuminate\*` is forbidden.
- One class per file; English names, documentation in English.

## Tests

- Add a test for the new value object/enum in `tests/` (namespace
  `OrmUniversal\Contracts\Tests\`).
- Verify equality, normalization, validations, and `__toString()` when applicable.

## Deliverables and verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint
```

Update `CHANGELOG.md` (Unreleased) and document the extension in the monorepo's
`docs/contracts.md` when it exists.
