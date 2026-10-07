# Changelog

All notable changes to `orm-universal/laravel` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.3] - 2026-10-07

### Added
- **Fast path AST en drivers `ude-*`**: cada driver resuelve a la conexión nativa con un
  `UdeQueryBuilder` que compila lecturas whitelisteadas al AST UDE y las ejecuta sobre el PDO de
  Laravel (mismos eventos, transacciones, read/write y tipos); las formas no soportadas delegan al
  builder nativo sin cambiar resultados. Incluye `cursor()` sin buffer y agregados directos.
- Activación opt-in con `auto.mode=intercept` / `UDE_AUTO=intercept` o `auto.intercept=true`;
  kill switch absoluto `UDE_AUTO_INTERCEPT=false`. `ude-oracle` queda passthrough documentado.
- Tests de paridad fila a fila (SQLite y MariaDB, decimales como string), eventos de modelo y
  `QueryExecuted`, fallback de formas no soportadas, gate tri-estado y kill switch.
- **`ude:auto:enable`**: convierte los `driver` de `config/database.php` a `ude-*` con
  `nikic/php-parser` (reemplazo quirúrgico del literal, sin reformatear el archivo). Flags
  `--connection=*`, `--path=`, `--dry-run` (default), `--diff`, `--backup`, `--interactive`,
  `--write` y guard de árbol git sucio (`--force`).
- **Plan cache** activado por defecto cuando intercept está activo (`cache.plan`, `plan_ttl`) y
  **reuso opt-in** de lecturas duplicadas (`UDE_QUERY_REUSE=true`, riesgo de lecturas stale
  documentado).
- `ude:index:advise --from-profiler`: recomendaciones de índices a partir de las muestras de
  N+1/consultas lentas del monitor automático.
- `flushDeferred()` en el flush de Octane/queue: los `defer()` pendientes se resuelven por lote en
  el límite del request.

## [0.1.2] - 2026-10-07

### Added
- `LaravelModelAdapter` construye las 11 familias de relaciones Eloquent (through, morphToMany/morphedByMany y morphTo con morph map explícito; polimórficas primero).
- `LaravelPaginatorAdapter`: convierte paginadores UDE en `LengthAwarePaginator`/`Paginator` nativos de Laravel.
- **Monitor automático (ON, riesgo cero)**: `VanillaMonitor` sobre `QueryExecuted` detecta N+1/queries duplicadas, lentas y sugiere eager-loading; comando `ude:auto:report` (console/JSON/Markdown) y limpieza en request/Octane.
- **Drivers de intercepción `ude-*` (passthrough)**: `ude-mysql`, `ude-mariadb`, `ude-pgsql`, `ude-sqlite`, `ude-sqlsrv`, `ude-oracle` registrados vía `DB::extend` con comportamiento idéntico a Laravel (verificado fila a fila en SQLite); base segura para el fast-path AST de la siguiente iteración.

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
