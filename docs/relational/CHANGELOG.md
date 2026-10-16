# Changelog

All notable changes to `orm-universal/relational` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-10-13

### Added
- Full-text: `whereFullText`/`orWhereFullText` con compilación por dialecto
  (`match ... against` en MySQL/MariaDB con modos boolean/expansión; `to_tsvector @@
  tsquery` en PostgreSQL con idioma y modos plain/phrase/websearch/raw) y excepción con
  alternativas en el resto.
- LATERAL joins: `joinLateral`/`leftJoinLateral` (PostgreSQL/MySQL 8.0.14+ con `on true`;
  SQL Server/Oracle como `cross apply`/`outer apply`; MariaDB/SQLite lanzan
  `UnsupportedFeatureException` con alternativas).
- `updateFrom(values, tablas)`: UPDATE ... FROM exclusivo de PostgreSQL (capability
  `supportsUpdateFrom`), con tablas validadas y bindings.

### Tests
- Golden SQL para full-text (MySQL/PostgreSQL), laterales (PostgreSQL/SQL Server y
  excepciones) y `update ... from` (PostgreSQL y excepción MySQL).

## [0.3.0] - 2026-10-13

### Added
- `QueryBuilder::inOrderOf()`: orden por lista de valores con CASE portable y bindings.
- `QueryBuilder::fromRaw()`: FROM crudo tipado (`SelectQuery::fromRaw`) integrado al
  fingerprint y al plan cache.
- `QueryBuilder::groupLimit()`: top-N por grupo con `ROW_NUMBER() OVER (PARTITION BY ...)`
  (nueva `WindowExpression` reutilizando el soporte existente).
- Index hints MySQL/MariaDB: `useIndex()`, `forceIndex()`, `ignoreIndex()` con validación
  de identificador y `UnsupportedFeatureException` con alternativas en el resto de dialectos.
- JSON avanzado: `whereJsonLength`/`orWhereJsonLength` y
  `whereJsonOverlaps`/`orWhereJsonOverlaps`/`whereJsonDoesntOverlap`/`orWhereJsonDoesntOverlap`
  con compilación por dialecto (`JSON_LENGTH`, `jsonb_array_length`, `json_array_length`,
  `openjson`) y gate de versión para `JSON_OVERLAPS` (MariaDB 10.9+).
- `Support\Macroable`: registry de macros nativo (`macro`, `hasMacro`, `flushMacros`,
  `mixin`, `macroCall`, `__call`) sin depender de Illuminate (ADR-001).

### Tests
- Golden SQL nuevo para inOrderOf, fromRaw, groupLimit, index hints (MySQL-only), JSON
  length/overlaps y macros; ejecución SQLite en `ParityBuilderTest`.
- Suite de paquetes: **208 tests / 741 aserciones** (1 skip MySQL).

## [0.2.0] - 2026-10-12

### Added
- Paridad Laravel 13 (optimize-9) en el builder fluido: LIKE con flag de sensibilidad
  (`whereLike/or/not`, `like binary` solo MySQL/MariaDB), null-safe equals
  (`whereNullSafeEquals` + or), comparación binaria (`whereBinary/NotBinary` + or),
  fechas y horas (`whereDate/whereTime` + or, `orWhereYear/Month/Day`) y azúcar temporal
  (`whereToday`, `before/afterToday`, `TodayOrAfter/Before`, `Past/Future`, `NowOrPast/Future` + or).
- `whereBetweenColumns/NotBetweenColumns`, `whereValueBetween/ValueNotBetween` y
  `whereRowValues` (+ variantes or) con nuevo nodo `Ast\RowValues`.
- Grupos `whereAll/whereAny/whereNone` (+ or); `whereIntegerInRaw/NotInRaw` (+ or).
- JSON: `whereJsonContainsKey`, `whereJsonDoesntContain`, `whereJsonDoesntContainKey`
  (+ or) y `orWhereJsonContains`.
- HAVING: `havingNull/NotNull/Between/NotBetween`, `orHavingRaw` (+ variantes or).
- OR simétricos faltantes: `orWhereRaw`, `orWhereColumn`, `orWhereNot`, `orWhereNotBetween`,
  `orWhereExists`, `orWhereNotExists`.
- Lectura y flujo: `find`, `findOr`, `soleValue`, `firstOrFail`, `raw`, `reorder`,
  `reorderDesc`, `inRandomOrder`, `forPage`, `forPageAfterId`, `forPageBeforeId`, `each`,
  `chunkMap`, `chunkByIdDesc`, `lazyByIdDesc`, `orderedChunkById`, `when`, `unless`,
  `tap`, `pipe`.
- JOIN: `crossJoinSub` (con `SelectQuery::crossJoinSub`), `joinWhere`, `leftJoinWhere`,
  `rightJoinWhere`.
- Escritura: `insertOrIgnore`, `insertUsing`, `insertOrIgnoreUsing`, `insertGetId`,
  `updateOrInsert`, `truncate`, `incrementEach`, `decrementEach`
  (con `InsertQuery::intoColumns()`).
- Dialectos: `supportsBinaryComparison()`, `randomFunction()`, `insertIgnore()` y
  `truncateStatements()`; partes `date`/`time` en `compileDatePart()` por dialecto.

### Fixed
- **`InsertQuery::ignore()` no compilaba**: el compilador ignoraba la bandera del AST.
  Ahora MySQL/MariaDB emiten `insert ignore`, SQLite `insert or ignore` y PostgreSQL
  `on conflict do nothing`; el resto lanza `UnsupportedFeatureException` con alternativas.
- Capability `supportsIsDistinctFrom` declarada en MySQL/MariaDB (el compilador `<=>`
  ya existía pero la negociación la rechazaba).
- Capability `supportsInsertIgnore` declarada en PostgreSQL (`ON CONFLICT DO NOTHING`).
- `TRUNCATE` inválido en SQLite: ahora emite `delete from` + reset de `sqlite_sequence`.

### Tests
- `ParityGoldenTest` (golden SQL MariaDB/PostgreSQL/SQLite/SQL Server) y
  `ParityBuilderTest` (ejecución SQLite): 26 tests nuevos. Suite de paquetes:
  **198 tests / 692 aserciones**.

## [0.1.7] - 2026-10-12

### Added
- API fluida para consultas complejas (optimize-8), toda sobre el AST existente:
  - `QueryBuilder::joinSub()/leftJoinSub()/rightJoinSub()` y `SelectQuery::joinSub()`:
    JOIN a subconsulta con alias obligatorio.
  - `QueryBuilder::fromSubquery()`/`fromTable()`: FROM derivado (`from(SelectQuery, alias)`).
  - `QueryBuilder::selectSub()`: subconsulta escalar proyectada con alias.
  - `whereIn()`/`whereNotIn()` aceptan subconsulta y `whereInSub()`/`whereNotInSub()`.
  - `whereExists()`/`whereNotExists()` aceptan `Closure` (builder sin FROM) además de
    `QueryBuilder|SelectQuery`; `whereNotBetween()`.
  - `having()`/`orHaving()` estilo Laravel de 3 argumentos y `havingRaw()`.
  - `selectRaw()`, `whereRaw()`, `groupByRaw()`, `orderByRaw()` con bindings explícitos.
  - `withRecursive()` para CTE recursivos.
- Ventanas con nombre: `WindowExpression::named(string)` ahora emite `over "nombre"` en lugar
  del `nodeType()` citado (bug); `windowName` en el constructor y `children()` completos.

### Fixed
- `SelectQuery::from()` limpia la tabla/subconsulta previa al reemplazar el origen (evita
  fingerprints y planes ambiguos al alternar tabla y subconsulta).
- `WindowExpression` con ORDER BY/frame ahora emite correctamente dentro de `over (...)` y
  las ventanas anidadas con nombre ya no generan SQL inválido.

### Tests
- `ComplexQueryGoldenTest` (golden SQL MySQL/PostgreSQL/SQLite) y
  `ComplexQueryExecutionTest` (SQLite real): 15 tests nuevos.

## [0.1.6] - 2026-10-07

### Added
- `Type\CastApplier` + `QueryBuilder::castWith()`: casts estilo Laravel en el core
  (`decimal:n` siempre string, JSON/array/object, enums backed, uuid, datetime, vector) aplicados
  antes de la hidratación en `get()`/`first()`.

### Fixed
- El plan cache (`QueryExecutor::compile` con `QueryCache`) usaba el fingerprint estructural del AST y
  podía devolver el SQL compilado de otra proyección/alias; ahora usa un fingerprint fuerte
  (`serialize` del AST) con regresión cubierta por test.

## [0.1.5] - 2026-10-07

### Added
- `QueryBuilder::exists()` con proyección directa `SELECT EXISTS(...)`: capability
  `supportsExistsProjection` en MySQL/MariaDB/PostgreSQL/SQLite; SQL Server y Oracle implementan
  `CASE WHEN EXISTS ... END` y quedan gated hasta integración real. Golden SQL de los 6 dialectos.
- `QueryBuilder::defer()` + `DeferredBatch`/`DeferredResult`: lecturas diferidas con resolución por
  lote al final del request y deduplicación por SQL + bindings.
- `QueryExecutor::withQueryReuse()` (opt-in) y `flushReuse()`; `flushDeferred()` para el flush de
  workers/Octane.
- Integración real de la proyección EXISTS en MariaDB y PostgreSQL (skip explícito si no hay servidor).

## [0.1.4] - 2026-10-07

### Fixed
- `LIMIT` portátil cuando solo hay `OFFSET`: SQLite emite `limit -1`, PostgreSQL `limit all` y
  MySQL/MariaDB mantienen el centinela de 64 bits. Antes el default rompía SQLite/PostgreSQL en
  consultas de solo offset.
- Los `JOIN` con tabla aliasada (`TableIdentifier` con alias) preservan el alias en el SQL compilado.

### Added
- Golden tests de solo-offset por dialecto y de alias de join.

### Performance
- `count()` con fast path directo (sin wrapper de subquery) para consultas simples.

## [0.1.3] - 2026-10-07

### Added
- **Familia completa de relaciones**: `hasOneThrough`, `hasManyThrough`, `morphToMany`/`morphedByMany` y `morphTo` (map explícito) en `RelationDefinition` + `RelationQuery` (join chain para *through*, filtro morfado en pivot, `morphToBatch` por tipo); `LaravelModelAdapter` construye las 11 familias con APIs públicas de Eloquent (polimórficas primero; MorphTo sin map no se genera).
- `simplePaginate()` sin total, `cursorPaginate()` keyset, `whereYear/whereMonth/whereDay` y `whereJsonContains` (subconjunto real en SQLite con JSON1).

## [0.1.2] - 2026-10-07

### Added
- **Relation Aggregate Engine**: `withCount()`, `withSum()`, `withAvg()`, `withMin()`, `withMax()`, `withExists()`, `withAggregate()` con closures y alias (semántica Eloquent: count=0, sum/avg/min/max=null, exists=false); `RelationAggregateCompiler` (subconsultas correlacionadas) y `RelationBatchLoader::loadAggregate()` (una consulta agrupada por FK).
- **Filtros por relación**: `has`, `orHas`, `doesntHave`, `orDoesntHave`, `whereHas`, `orWhereHas`, `whereDoesntHave`, `withWhereHas` vía EXISTS + `usingRelations(RelationRegistry)`; soft-deletes solo si la definición lo declara.
- **Agregados de builder**: `sum()`, `avg()`, `min()`, `max()`.
- **Paridad OO**: `valueOrFail`, `existsOr`, `doesntExist(Or)`, `sole`, `firstOrNew`, `firstOrCreate`, `createOrFirst`, `updateOrCreate`, `create`, `increment`, `decrement`, `whereNot`, `orWhereIn/NotIn/Null/NotNull/Between`.
- **Keyset**: `chunkById`, `eachById`, `lazyById` (sin offset drift).
- `RelationCollection`: `sum/avg/min/max` locales.

## [0.1.1] - 2026-10-06

### Added
- Contexts: `QueryContext`, `ExecutionContext`, `RequestContext`, `TransactionContext`,
  `HydrationContext`, `BusinessContext`.
- Business engine: `BusinessScope`, `BusinessOperation`, `BusinessRule`,
  `BusinessMethodResolver`, `MethodCache` (traceable, cacheable resolution).
- Relation engine: `RelationDefinition`, `RelationQuery`, `RelationHydrator`,
  `RelationCollection`, `RelationOptimizer`, `RelationRegistry` (JOIN vs batch IN vs
  chunked IN vs subquery planning).
- Recordset modes: `ObjectRecordset`, `EntityRecordset`, `EloquentRecordset`,
  `LazyRecordset`, `CursorRecordset`.
- Optimizer pipeline: `Normalizer`, `Validator`, `ProjectionPruningPass`,
  `PortabilityAnalyzer`, `PerformanceAnalyzer`.
- Type helpers: `TypeResolver`, `TypeCaster`, `TypeSerializer`, `TypeDeserializer`
  and database specific type aliases (postgresqlJsonb, mysqlJson, sqlserver
  uniqueidentifier, oracle number...).
- Execution: `BatchPlanner` (placeholder aware batching), `DestructiveStatementPolicy`.
- Support: `CrossDatabaseCapability` guard for cross-connection joins.
- Cache: `QueryCache` (dialect+version scoped keys) and `SchemaCache`.
- Plugin: `PluginRegistry` for dialects/types/optimizers/business contributions.
- Builder API: `eloquent()`, `ast()`, `dialect()`, `capabilities()`, `explain()`,
  `portability()`, `performance()`, `insertMany()`, `upsertMany()`, `updateMany()`,
  `deleteMany()`, `batchPlanner()`, `usingPolicy()`, `confirmDestructive()`.

### Tooling
- Documentación completa en `docs/` (28 documentos + 10 escenarios de migración), gobernanza, `AGENTS.md`, skill de agentes, prompts y ejemplos ejecutables (Basic → Extreme).
- Release automation: workflow con auto-versionado por Conventional Commits (pre-1.0: feat→patch, breaking→minor) y `tools/release.php`.
- Benchmarks reproducibles `benchmarks/benchmark.php` (PDO vs UDE zero/light/cursor/count) con umbral de regresión.
- CI multi-motor: contrato por dialecto contra MariaDB 11.6, MySQL 8.4 y PostgreSQL 18 + artefacto de benchmark.
- Se eliminó el campo `version` de `composer.json`: la versión proviene del tag (SemVer).

## [0.1.0] - 2026-10-06

### Added
- Query AST (`SelectQuery`, `InsertQuery`, `UpdateQuery`, `DeleteQuery`, `RawQuery`).
- 16 expression types plus predicates, aggregates, window, JSON and date expressions.
- SQL compiler with identifier quoting, value bindings and raw type separation.
- Capability engine per vendor/version and `CapabilityNegotiator`.
- Six dialects: MySQL 8.0–26.x, MariaDB 10.6–13.x, PostgreSQL 14–18,
  SQLite 3.35–3.54, SQL Server 2019–2025, Oracle 19c–26ai.
- PDO driver layer: connection, prepared statement cache, cursor, transactions,
  savepoints, multi-connection manager and profiler hooks.
- Query builder (fluent + object oriented) converging on the same AST.
- Recordset engine: array and streaming recordsets with chunk/lazy/paginate.
- Hydration modes: zero, light, entity and factory.
- Schema inspectors (MariaDB/MySQL, PostgreSQL, SQLite, SQL Server, Oracle) and diff.
- Migration compiler: create/drop/alter/index/foreign key/rename/truncate per dialect.
- Optimizer with duplicate predicates, boolean simplification and constant folding.
- Relation engine: metadata, batch loader, graph and N+1 detector.
- Business registry and composable specifications.
- Type registry with universal types mapped per dialect.
- Immutable Octane-safe contexts and in-memory profiler.
- Golden SQL/DDL tests plus real SQLite integration tests.
