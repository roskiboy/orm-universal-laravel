# Changelog

All notable changes to `orm-universal/laravel` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-10-06

### Added
- `LaravelVersionResolver` exposing runtime version data plus feature detection.
- `LaravelContainerAdapter` implementing PSR-11 over the Laravel container.
- `LaravelModelAdapter`: model metadata and first class relation definitions
  (belongsTo, hasOne, hasMany, belongsToMany, morphOne/morphMany) without modifying
  the model; MorphTo is intentionally not guessed.
- `LaravelBuilderAdapter`: copies known Eloquent clauses (Basic/Null/In/Between/Column,
  orders, limit, offset) into a UDE query; unknown shapes are skipped, never guessed.
- `LaravelMigrationAdapter`: DDL preview, destructive safety report, guarded execution
  and transactional execution when the dialect supports transactional DDL.
- Plugin registry exposed through the kernel (`Ude::plugins()`, `Ude::bootPlugins()`).

### Changed
- Feature detection hardened: reflection based method detection plus `is_callable`
  for macro support; no version checks anywhere.

### Tooling
- Documentación completa en `docs/`, gobernanza, `AGENTS.md`, skill de agentes, prompts y ejemplos (fragmentos Laravel).
- Release automation con auto-versionado por Conventional Commits y `tools/release.php`.
- Benchmark Eloquent vs Query Builder vs UDE (`benchmarks/benchmark.php`) con verificación de regresión y artefacto en CI.
- Nuevos comandos: `ude:docs:api`, `ude:index:advise`, `ude:stubs`.
- Se eliminó el campo `version` de `composer.json`: la versión proviene del tag (SemVer).

## [0.1.0] - 2026-10-06

### Added
- `OrmUniversalServiceProvider` with auto-discovery, config merge and publish
  (`orm-universal-config`).
- `Ude` kernel with dialect/type/business registries, context scope, profiler,
  optimizer and NoSQL store.
- `LaravelConnectionAdapter` reusing the live PDO handle and detected server version.
- `LaravelFeatureRegistry` (feature detection, never version checks).
- `LaravelCompatibilityLayer` with six adoption modes and strict-mode guards.
- Eloquent bridge: `HasUniversalQueries` trait plus `universal()` and `recordset()`
  builder macros. Eloquent is never modified and no filters are injected.
- Octane/queue context resets on `RequestTerminated`, `TaskTerminated`,
  `TickTerminated`, `JobProcessed` and `JobFailed`.
- 16 `ude:*` artisan commands for install, diagnostics, analysis, schema, migrations,
  inspection, compatibility, benchmark and cache management.
- `OrmUniversal` facade.
