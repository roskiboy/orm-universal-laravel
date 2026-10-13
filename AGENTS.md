# AGENTS.md — orm-universal/laravel

Guide for AI agents and automated contributors.

## Minimal architecture

```text
src/
├── OrmUniversalServiceProvider.php   (auto-discovery, config, commands, macros, Octane)
├── Ude.php                           (kernel: registries, contexts, profiler, NoSQL)
├── Adapters/                         (connection, feature registry, model, builder, migration)
├── Compatibility/                    (six adoption modes)
├── Concerns/HasUniversalQueries.php  (optional trait for models)
├── Console/                          (16 ude:* commands)
├── Analysis/                         (ModelInspector, ProjectAnalyzer, Codemod)
├── Facades/OrmUniversal.php
└── config/orm-universal.php
```

Depends on `contracts`, `relational`, `nosql`, and `illuminate/console|database|support`.
The Core of those packages never imports `Illuminate\*`; the dependency exists only in
this layer.

## Main rule: do not break vanilla Eloquent

1. **Modifying Eloquent behavior is forbidden.** The `universal()` and `recordset()`
   macros are additive; they never inject filters or alter results, order, casts,
   events, or transactions.
2. **There are no global interceptors.** If the user does not invoke the platform, the
   behavior must be pure Laravel.
3. **Do not guess clauses.** `LaravelBuilderAdapter` copies only what it understands;
   the unknown is omitted.
4. **Do not touch models.** `LaravelModelAdapter` reads metadata by reflection without
   running the model constructor or modifying it.
5. **Detect by features, never by version.** Use `LaravelFeatureRegistry`
   (`class_exists`, reflection). Comparing Laravel version numbers is forbidden.
6. **Per-request state.** Any accumulated state must be cleared in `Ude::flush()`,
   invoked from the Octane/queue events.

## Repo rules

- `declare(strict_types=1)` in every file.
- Commands are conservative: dry-run by default; `--write` and `--force` explicit.
- The guarantees of `LaravelCompatibilityLayer::report()` are not relaxed without a
  major release and a migration guide.
- Do not add dependencies outside `illuminate/*` and the `orm-universal/*` packages.

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint
```

Gates: all four with no errors. CI tests PHP 8.3–8.5; the Laravel layer tests run
against the installed framework.

## Conventions

- PSR-4: `OrmUniversal\Laravel\` → `src/`; tests `OrmUniversal\Laravel\Tests\`.
- Commands: `ude:<area>:<action>` signature and description in Spanish as in the rest of
  the package console.
- Class and method names in English; package docs in English and console text in Spanish.
- `final class` by default; stateless adapters except for explicit caches.

## When modifying adapters

1. Add/update a test in `tests/AdaptersTest.php` or `tests/CompatibilityTest.php`.
2. Verify that Eloquent is still intact (result, order, nulls).
3. Update `docs/eloquent.md`, `docs/relations.md`, or `docs/hybrid-mode.md` as
   appropriate.

## Do not touch

- Do not edit `README.md`, release `CHANGELOG.md`, or `LICENSE` without instruction.
- Do not modify `.github/` or `composer.json` except for a release task.
