# Prompt: migrate code coupled to Eloquent toward UDE contracts (without breaking Eloquent)

## Context

A Laravel app has persistence logic coupled to the ORM (engine version comparisons,
unclassified raw SQL, cache keys based on the textual SQL). The goal is to extract
that logic into framework-independent code using only `OrmUniversal\Contracts`, leaving
Eloquent intact.

## Goal

Ask an agent to:

1. Identify in the Laravel code the points that talk about engines/capabilities/versions.
2. Replace loose strings and booleans with `CapabilitySet`, `Capability`, and `DialectVersion`.
3. Capture raw SQL and classify it with `SqlFragment` + `RawType`.
4. Centralize query identity with `QueryFingerprint` (cache, logs, alerts).
5. Standardize errors with `UnsupportedFeatureException` and its `report()`.

## Rules for coexisting with Eloquent

- Do not touch models, relations, casts, events, or migrations in this step.
- Do not introduce `Illuminate\*` into the new classes: they must be testable without booting Laravel.
- Eloquent keeps executing the queries; the contracts only describe intent and
  capabilities. Universal execution comes later with `OrmUniversal\Relational`.
- Keep parity: every change must pass the app's existing suite.

## Suggested steps

1. `rg "version_compare|supports|SERVER_VERSION" app/` to locate couplings.
2. Create a negotiation class (`BackendCapabilities`) that receives `DialectVersion` and
   returns `CapabilitySet`.
3. Wrap raw SQL in `SqlFragment::parameterized()` when it has bindings.
4. Replace cache keys with `(string) QueryFingerprint::fromSql($sql, $bindings)`.
5. Add tests with `InMemoryDriver`/SQLite when applicable.

## Deliverables

- New classes that depend only on `OrmUniversal\Contracts\*`.
- Parity tests (same Eloquent result) and negotiation tests (missing feature reported).
- Report of migrated and pending points.

## Verification

```bash
php artisan test
vendor/bin/phpstan analyse
```

## Boundary

If a point requires actually compiling/executing, that step belongs to
`OrmUniversal\Relational` (see its `migrate-from-eloquent.md` prompt).
