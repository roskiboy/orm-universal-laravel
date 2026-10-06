# Changelog

All notable changes to `orm-universal/laravel` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
