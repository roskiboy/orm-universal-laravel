# Changelog

All notable changes to `orm-universal/contracts` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-10-13

### Added
- `ComparisonOperator::JsonOverlaps` (`json_overlaps`) para la paridad Laravel 13 de
  solapamiento de arreglos JSON (optimize-10).

## [0.2.0] - 2026-10-12

### Added
- `ComparisonOperator::BinaryEq`, `BinaryNeq` (`= binary` / `!= binary`) y
  `LikeBinary`, `NotLikeBinary` (`like binary` / `not like binary`) para la paridad
  Laravel 13 de comparaciones binarias y LIKE sensible a mayúsculas (optimize-9).

## [0.1.2] - 2026-10-07

### Added
- `Capability::EXISTS_PROJECTION` (`supportsExistsProjection`) para negociar la proyección directa
  de `EXISTS` en el select list.

## [0.1.1] - 2026-10-06

### Added
- Documentación completa en `docs/`, gobernanza (SECURITY, CONTRIBUTING, CODE_OF_CONDUCT, ROADMAP, RELEASES, DOCUMENTATION), `AGENTS.md`, skill de agentes, prompts y ejemplos ejecutables (Basic → Extreme).
- Workflow de release con auto-versionado por Conventional Commits y herramienta local `tools/release.php`.

### Changed
- Se eliminó el campo `version` de `composer.json`: la versión proviene del tag (SemVer).

## [0.1.0] - 2026-10-06

### Added
- Service interfaces for dialect, compiler, migration compiler, driver, connection,
  statement, cursor, transaction, query object, expression, AST node, recordset,
  hydrator, entity, type registry, schema inspector/builder, optimizer pass,
  capability negotiator, context, specification, business registry, plugin,
  profiler, relation definition/loader and paginator.
- Enums for capabilities, vendors, families, hydration/recordset modes, portability,
  transaction state, performance/compatibility modes, N+1 modes, operators,
  aggregate functions, index/constraint types and type kinds.
- Value objects: identifiers, `SqlFragment`, `DialectVersion`, `CapabilitySet`,
  `QueryFingerprint`, `CompiledQuery`, `CompiledExpression`, `PortabilityReport`,
  `PerformanceReport`, `Ordering`, `ConnectionConfig`, `ColumnDefinition`,
  `IndexDefinition`, `ForeignKeyDefinition`, `TableDefinition`, `SchemaSnapshot`,
  `QueryProfileEvent`, `ParameterBinding`.
- Exception hierarchy with `UnsupportedFeatureException::report()`.
- `ApiStatus` attribute for lifecycle/deprecation metadata.
