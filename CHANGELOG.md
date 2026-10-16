# Changelog

All notable changes to `roskiboy/orm-universal-laravel` (ORM Universal Laravel) are
documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Historical per-layer changelogs (before the unification) are preserved under
[`docs/contracts/CHANGELOG.md`](docs/contracts/CHANGELOG.md),
[`docs/relational/CHANGELOG.md`](docs/relational/CHANGELOG.md),
[`docs/nosql/CHANGELOG.md`](docs/nosql/CHANGELOG.md) and
[`docs/laravel/CHANGELOG.md`](docs/laravel/CHANGELOG.md).

## [Unreleased]

## [0.7.4] - 2026-10-16

### Fixed
- `tools/release.php --notes` ahora lee la sección `## [X.Y.Z]` del CHANGELOG cuando
  `[Unreleased]` ya fue promovido por el propio workflow de release (antes el paso
  "Create GitHub release" fallaba con "Nothing to release: [Unreleased] is empty").

## [0.7.3] - 2026-10-16

### Changed
- Release workflow: el push del bot usa un deploy key incluido en el bypass del ruleset
  `main-protection` (la app "GitHub Actions" no es elegible para el bypass); pasos de
  configuración en `RELEASES.md`.

### Security
- Workflows endurecidos: `permissions: contents: read` en CI, actions fijadas por SHA y
  `composer audit` en el job de tests.
- Dependabot (composer y github-actions), `CODEOWNERS` y plantillas de PR/issue.

## [0.7.2] - 2026-10-16

### Fixed
- Tests: sintaxis compatible con PHP 8.3 en `PlatformExtrasTest` (el encadenado
  `new QueryExecutor(...)->withQueryCache()` requiere paréntesis envolventes antes de
  PHP 8.4).
- Tests: los tests de integración usan el atributo `#[Group('integration')]` (PHPUnit 12
  ya no lee `@group` en docblocks, y el job de CI terminaba con "No tests executed").
- CI: MySQL 8.4 se levanta con `docker run` y espera explícita, porque el runner no logra
  inicializarlo como service container.

## [0.7.1] - 2026-10-16

### Fixed
- Autoría del paquete a `roskiboy` (GitHub) en `composer.json`, `LICENSE`,
  `SECURITY.md` y `CODE_OF_CONDUCT.md`.

## [0.7.0] - 2026-10-16

### Changed
- Fusión de los cuatro paquetes (`orm-universal/contracts`, `orm-universal/relational`,
  `orm-universal/nosql`, `orm-universal/laravel`) en un solo paquete y repositorio:
  **`roskiboy/orm-universal-laravel`** (ORM Universal Laravel). El paquete declara
  `replace` de los cuatro nombres anteriores.
- PSR-4 intacto: `OrmUniversal\Contracts\`, `OrmUniversal\Relational\`, `OrmUniversal\NoSql\`
  y `OrmUniversal\Laravel\`; solo cambian las rutas físicas a `src/<Capa>/`.
- Un solo `composer.json`, `phpunit.xml.dist` (4 suites), `phpstan.neon.dist`, workflow de
  CI y de release; benchmarks en `benchmarks/relational` y `benchmarks/laravel`.

### Fixed
- `ude:parity:data` producía falsos mismatches en modelos con `SoftDeletes`
  (p. ej. `TreasuryMovement`/`BankMovement`): el lado vanilla aplicaba el scope global
  (excluye trashed) mientras UDE lee la tabla cruda. Ahora neutraliza el scope
  (`withoutGlobalScope(SoftDeletingScope::class)`) y ordena por PK en ambos lados
  (optimize-13).

## [0.5.0] - 2026-10-13

### Added
- **optimize-12:** cierre de todos los diferidos de paridad Laravel 13 con la política
  nativo → emulado con `CompilationWarning` → excepción (ADR-016): `straightJoin*`, `timeout`,
  `insertOrIgnoreReturning` y vector. `updateFrom` y `whereJsonOverlaps` con traducción
  multi-dialecto.
- Magia controlada (ADR-017): paths JSON en strings, UDF (`Expression::func`/`OrmUniversal::udf`),
  RAW (`OrmUniversal::raw()`) y bulk (`createMany`/`updateOrCreateMany`/`insertOrIgnoreMany`).
- `ude:parity:api` reporta **0 diferidos** en 6 fuentes (Query/Eloquent Builder, Model,
  Relation, Schema Builder/Blueprint).

### Changed
- Paquetes `relational`/`laravel` a **0.5.0**; `contracts`/`nosql` a **0.4.0**.
- Rendimiento: `InToEqualsPass` y `ProjectionPruningPass` por defecto; `BatchPlanner` adapta
  lotes a potencias de dos; statement cache LRU en `PdoConnection`.
- Documentación de paquetes en inglés; `ude:parity:data {model}` valida fila a fila vanilla vs UDE.

## [0.4.0] - 2026-10-13

### Added
- **optimize-11:** `relational` 0.4.0 agrega full-text (`match against` / `to_tsvector`),
  LATERAL joins (`joinLateral`/`leftJoinLateral` con `cross/outer apply` en SQL Server y
  Oracle) y `updateFrom` (PostgreSQL). `orm-universal/laravel` intercepta `Fulltext`.
- App: UDE read paths para `buildStatementData` y `resolveDailyIndicators`
  (`UDE_HOTPATH_BANK_STATEMENT`, `UDE_HOTPATH_DAILY_INDICATORS`) con paridad fila a fila, y
  exports PDF/Excel grandes en cola (`GenerateTreasuryExport` + endpoints con token y URL
  firmada).

### Changed
- Paquetes `relational` y `laravel` a **0.4.0**; `contracts` y `nosql` permanecen en 0.3.0.
- Diferidos de paridad: 10 (straight join, timeout, insertOrIgnoreReturning y vector).

## [0.3.0] - 2026-10-13

### Added
- **optimize-10:** `relational` cierra la API avanzada de Laravel 13: `inOrderOf`,
  `fromRaw`, `groupLimit` (ROW_NUMBER por partición), index hints MySQL/MariaDB,
  JSON length/overlaps por dialecto y registry de macros nativo (ADR-001).
- `contracts`: `ComparisonOperator::JsonOverlaps`.
- `orm-universal/laravel`: `UdeQueryMapper` ampliado (fechas, JSON, `Exists/whereHas`,
  `Not`, InRaw, row values, betweenColumns/valueBetween, null-safe) con paridad fila a fila
  y cobertura de `dynamicWhere`/macros sobre drivers `ude-*`.

### Changed
- Paquetes alineados a **0.3.0** (constraints internos `^0.3` y pin del anfitrión).
- Suite de paquetes: **208 tests / 741 aserciones**.

## [0.2.0] - 2026-10-12

### Added
- **Paridad Laravel 13 (optimize-9):** `relational` agrega predicados (LIKE con
  sensibilidad, null-safe equals, binario, fechas/horas y azúcar temporal,
  between-columns, value-between, row values, all/any/none, JSON key/doesnt-contain,
  HAVING extendido, OR simétricos), lectura y flujo (`find/findOr/soleValue/firstOrFail`,
  `reorder`, `inRandomOrder`, `forPage*`, `each`, `chunkMap`, `*ByIdDesc`,
  `orderedChunkById`, `when/unless/tap/pipe`), joins (`crossJoinSub`, `joinWhere*`) y
  escritura (`insertOrIgnore`, `insertUsing`, `insertOrIgnoreUsing`, `insertGetId`,
  `updateOrInsert`, `truncate`, `incrementEach`, `decrementEach`).
- `orm-universal/laravel`: comando **`ude:parity:api`** + `Analysis\ParityAnalyzer`
  (auditoría de paridad contra Laravel 13 con clasificación por reglas).
- `orm-universal/contracts`: nuevos `ComparisonOperator` (`BinaryEq`, `BinaryNeq`,
  `LikeBinary`, `NotLikeBinary`).

### Fixed
- `InsertQuery::ignore()` no compilaba (`insert ignore` / `insert or ignore` /
  `on conflict do nothing` por dialecto).
- Capabilities `supportsIsDistinctFrom` (MySQL/MariaDB) y `supportsInsertIgnore`
  (PostgreSQL) ausentes en la negociación.
- `TRUNCATE` inválido en SQLite (ahora `delete from` + reset de secuencia).
- `UdeQueryMapper` acepta joins multi-condición en el `ON` con paridad fila a fila.

### Changed
- Paquetes alineados a **0.2.0** (constraints internos `^0.2` y pin del anfitrión).
- Suite de paquetes: **198 tests / 692 aserciones**.

## [0.1.7] - 2026-10-12

### Added
- Relational: API fluida para consultas complejas sobre el AST existente
  (`joinSub/leftJoinSub/rightJoinSub`, `fromSubquery/fromTable`, `selectSub`,
  `whereIn/whereNotIn` con subconsulta + `whereInSub/whereNotInSub`,
  `whereExists/whereNotExists` con `Closure`, `having(col, op, val)`/`orHaving`,
  `selectRaw/whereRaw/groupByRaw/orderByRaw`, `withRecursive`, `whereNotBetween`).
- Relational: golden SQL e integración SQLite para subconsultas, HAVING con agregados,
  windows con ORDER BY/frame, CTE recursivos y fragmentos raw con bindings
  (`ComplexQueryGoldenTest`, `ComplexQueryExecutionTest`, 15 tests).

### Fixed
- Relational: `WindowExpression::named()` compila `over "nombre"` (antes citaba el
  `nodeType()` y generaba SQL inválido); ORDER BY/frame dentro de `over (...)`.
- Relational: `SelectQuery::from()` limpia la fuente previa al alternar tabla/subconsulta.

### Changed
- Suite de paquetes: 167 tests / 574 aserciones (1 skip MySQL 3307). PHPStan 0, Pint OK.

## [Unreleased] (histórico previo a 0.1.7)

### Added
- Relational: dedicated contexts (`QueryContext`, `ExecutionContext`, `RequestContext`,
  `TransactionContext`, `HydrationContext`, `BusinessContext`), business engine
  (`BusinessScope`, `BusinessOperation`, `BusinessRule`, `BusinessMethodResolver`,
  `MethodCache`), relation engine completion (`RelationDefinition`, `RelationQuery`,
  `RelationHydrator`, `RelationCollection`, `RelationOptimizer`, `RelationRegistry`),
  recordset modes (`ObjectRecordset`, `EntityRecordset`, `EloquentRecordset`,
  `LazyRecordset`, `CursorRecordset`), optimizer pipeline (`Normalizer`, `Validator`,
  `ProjectionPruningPass`), `PortabilityAnalyzer`, `PerformanceAnalyzer`, type helpers
  (`TypeResolver`, `TypeCaster`, `TypeSerializer`, `TypeDeserializer`), database
  specific type aliases, `BatchPlanner`, `DestructiveStatementPolicy`,
  `CrossDatabaseCapability`, `QueryCache`, `SchemaCache`, `PluginRegistry`.
- Relational builder API: `eloquent()`, `ast()`, `dialect()`, `capabilities()`,
  `explain()`, `portability()`, `performance()`, `insertMany()`, `updateMany()`,
  `upsertMany()`, `deleteMany()`, `usingPolicy()`, `confirmDestructive()`.
- Laravel: `LaravelVersionResolver`, `LaravelContainerAdapter` (PSR-11),
  `LaravelModelAdapter`, `LaravelBuilderAdapter`, `LaravelMigrationAdapter` and plugin
  registry in the kernel.
- Nosql: strict typing cleanups in the in-memory and Redis drivers.

### Changed
- PHPStan level 5 now reports **0 errors** across the packages; three documented
  exceptions remain in `phpstan-packages.neon`.
- Test suite growth: 84 package tests (SQLite/Redis/MongoDB integration included).

## [0.1.0] - 2026-10-06

### Added
- `orm-universal/contracts`: interfaces, enums, value objects y 13 excepciones.
- `orm-universal/relational`: AST y expresiones (16 tipos), compilador SQL con separación
  value/identifier/raw, capability engine, 6 dialectos versionados (MySQL 8.x–9.x,
  MariaDB 10.6–13.x, PostgreSQL 14–18, SQLite 3.35–3.54, SQL Server 2019–2025,
  Oracle 19c–26ai), PDO driver layer con statement cache, recordsets array/streaming,
  hidratación zero/light/entity/factory, query builder fluido y OO, schema inspectors,
  motor de migraciones DDL, optimizador con 3 passes, relaciones con batch loader y
  detector N+1, business registry y specifications, sistema de tipos, contextos
  Octane-safe, profiler en memoria.
- `orm-universal/nosql`: DocumentStore, Filter, Aggregation\Pipeline y drivers
  InMemory (tested), MongoDB (tested con ext 2.4.0 + lib 2.4.2) y Redis (tested con 5.0.14).
- `orm-universal/laravel`: service provider con auto-discovery, adapters de conexión,
  modo híbrido, macros Eloquent y 16 comandos `ude:*`.
- Instalación en la aplicación `tms`: path repositories, configuración publicada,
  reportes reales (25 tablas, 17 modelos, 36 migraciones), test de paridad híbrida
  `tests/Feature/Ude/SellerUdeTest.php`, benchmark MariaDB.

### Fixed
- `phpunit.xml`: `BANKING_ENABLED=true` para desacoplar la suite del `.env` local.
- `OctaneDeploymentTest`: puerto dinámico `IIS_OCTANE_PORT` en lugar de 4436 fijo.
- OPcache CLI desactivado en el php.ini local (workaround PHP 8.5.1, ver ADR-011).

### Security
- Valores siempre como bindings; identificadores citados y validados; raw SQL explícito
  (`SqlFragment::trusted|parameterized|identifier|value`); sufijos ORDER BY validados.

### Known limitations
- SQL Server / Oracle: implementación completa de dialecto/DDL, integración pendiente de
  infraestructura (no se declaran "Stable").
- PHPStan nivel 5: 35 hallazgos residuales declarados (ADR-012).
- Redis 5.0.14 (EOL): capacidades modernas 6.x–8.x documentadas pero no testeadas aquí.
