# Changelog

All notable changes to `orm-universal/nosql` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-10-13

### Changed
- Alineación de dependencias a `orm-universal/contracts ^0.3` (optimize-10). Sin cambios
  funcionales en el motor NoSQL.

## [0.2.0] - 2026-10-12

### Changed
- Alineación de dependencias a `orm-universal/contracts ^0.2` (optimize-9). Sin cambios
  funcionales en el motor NoSQL.

## [0.1.1] - 2026-10-06

### Fixed
- Strict typing cleanups in `InMemoryDriver` (aggregation grouping) and `RedisDriver`
  (string id normalization), passing PHPStan level 5 with zero errors.

### Tooling
- Documentación completa en `docs/`, gobernanza, `AGENTS.md`, skill de agentes, prompts y ejemplos ejecutables (InMemory + Mongo/Redis opcionales).
- Release automation con auto-versionado por Conventional Commits y `tools/release.php`.
- Se eliminó el campo `version` de `composer.json`: la versión proviene del tag (SemVer).

## [0.1.0] - 2026-10-06

### Added
- `Document`, `DocumentStore`, `Collection` and fluent `Filter` with native operators.
- `Aggregation\Pipeline` with match/group/sort/limit/skip/project/unwind/stage.
- `DocumentDriverInterface` with per-driver `CapabilitySet`.
- `InMemoryDriver`: reference implementation with full filter evaluation and pipeline.
- `MongoDbDriver`: native pipelines, CRUD, transactions, change streams, TTL, geo,
  text and time-series capabilities (requires ext-mongodb + mongodb/mongodb).
- `RedisDriver`: JSON documents over key-value with TTL/pub-sub/streams/Lua
  capabilities; aggregation explicitly unsupported with documented alternatives.
- Unit tests for InMemory plus real integration tests for MongoDB and Redis
  (skipped automatically when server/extension is unavailable).
