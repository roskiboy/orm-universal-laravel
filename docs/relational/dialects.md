# Dialects

A dialect converts the AST into concrete SQL and declares what each version supports.
All of them extend `OrmUniversal\Relational\Dialect\AbstractDialect`.

## Creating a dialect

```php
use OrmUniversal\Relational\Dialect\MariaDbDialect;
use OrmUniversal\Relational\Dialect\MySqlDialect;
use OrmUniversal\Relational\Dialect\OracleDialect;
use OrmUniversal\Relational\Dialect\PostgreSqlDialect;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Dialect\SqlServerDialect;

MySqlDialect::make();          // 8.4.0 by default
MariaDbDialect::make();        // 11.6.2
PostgreSqlDialect::make();     // 18.0
SqliteDialect::make();         // 3.49.2
SqlServerDialect::make();      // 2022
OracleDialect::make();         // 19.0
```

Supported lines (README): MySQL 8.0–26.x, MariaDB 10.6–13.x, PostgreSQL 14–18,
SQLite 3.35–3.54, SQL Server 2019–2025, Oracle 19c–26ai.

## AbstractDialect

`version(): DialectVersion`, `capabilities(): CapabilitySet`, `family()`,
`supports(string $capability): bool`, `requiredCapabilities(QueryObjectInterface)`,
`compiler()`, `compile(QueryObjectInterface): CompiledQuery`,
`compileExpression(ExpressionInterface)`, `migrationCompiler()`, `limitations()`.

Hooks each vendor can override: `quoteChar()`/`quoteIdentifier()`,
`escapeStringLiteral()`, `booleanLiteral()`, `limitOffset()`,
`updateDeleteLimit()`, `lockClause()`, `returningClause()`, `outputClause()`,
`upsertStyle()`, `upsertSuffix()`, `functionName()`, `compileIlike()`,
`compileRegex()`, `compileIsDistinctFrom()`, `compileDatePart()`,
`compileJson()`, `compileJsonContains()`, `compileJsonContainsKey()`,
`compileAggregate()`.

Each dialect documents its real limits in `limitations()`, for example:
MySQL without `RETURNING`; SQLite single-writer without locks or `LATERAL`; PostgreSQL
without `UPDATE/DELETE LIMIT`; SQL Server with mandatory `OFFSET/FETCH` together with
`ORDER BY`; Oracle with auto-commit DDL.

## CapabilitySet per version

`CapabilitySet` is immutable: `of()`, `empty()`, `supports()`, `value()`,
`with()`, `without()`, `merge()`, `all()`, `missing()`, `diff()`,
`requireAll()`. `Capability` centralizes the canonical names.

Real cutoffs implemented per version:

| Engine | Milestone | Capability |
|---|---|---|
| MySQL | 8.0 | CTE, window functions, JSON, JSON_TABLE, SKIP LOCKED |
| MySQL | 8.0.13 / 8.0.16 | functional index / CHECK enforced |
| MySQL | 8.0.14 | LATERAL |
| MySQL | 9.0 | VECTOR |
| MariaDB | 10.2 | CTE, window functions, JSON, CHECK |
| MariaDB | 10.3 | sequences, PERCENTILE |
| MariaDB | 10.5 | RETURNING |
| MariaDB | 10.6 | SKIP LOCKED |
| MariaDB | 11.7 | VECTOR |
| PostgreSQL | 15 | MERGE |
| PostgreSQL | 17 | JSON_TABLE |
| SQLite | 3.24 / 3.25 | UPSERT / window functions |
| SQLite | 3.35 | RETURNING |
| SQL Server | 2025 | VECTOR, REGEXP, JSON_TABLE |
| Oracle | 23+ | VECTOR, JSON operators |

Real limits: `MAX_PLACEHOLDERS` 65535 (MySQL/MariaDB/PostgreSQL/Oracle),
32766 or 999 in SQLite depending on the build, 2100 in SQL Server;
`MAX_IDENTIFIER_LENGTH` 64 (MySQL/MariaDB), 63 (PostgreSQL), 255 (SQLite),
128 (SQL Server/Oracle ≥12.2), 30 (earlier Oracle).

## Registry and resolution

```php
use OrmUniversal\Relational\Dialect\DialectRegistry;
use OrmUniversal\Relational\Dialect\DialectResolver;

$registry = DialectRegistry::withDefaults();
$registry->has(DialectVendor::Mysql);
$dialect = $registry->for(DialectVersion::parse(DialectVendor::Mysql, '8.4.3'));

$dialect = DialectResolver::resolve('pgsql', '18.1');
DialectResolver::vendorFromDriver('mariadb');
DialectResolver::versionFromRaw(DialectVendor::SqlServer, '16.0.1000.6'); // 2022
```

`DialectRegistry::register(DialectVendor $vendor, callable $factory, bool $override = false)`
allows adding vendors or replacing a line. `DialectVersion::parse()` and
`atLeast()`/`numeric()` drive the negotiation.

## Missing capability: `UnsupportedFeatureException`

`CapabilityNegotiator::negotiate()` analyzes the AST with
`CapabilityAnalyzer::forNode()` and throws
`OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException` before
compiling. The exception exposes `feature`, `engine`, `engineVersion`, `reason` and
`alternatives`, and `report()` returns the full report.

```php
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;

try {
    MySqlDialect::make('8.4.3')->compile(
        \OrmUniversal\Relational\Ast\InsertQuery::into('users')
            ->values(['name' => 'ana'])
            ->returning(['id']),
    );
} catch (UnsupportedFeatureException $e) {
    echo $e->report();
    // Feature: supportsReturning
    // Database: mysql
    // Server: 8.4.3
    // Reason: Capability not supported ...
    // Alternatives: Follow-up SELECT using lastInsertId() ...
}
```

Registered alternatives: `GROUP_CONCAT` ↔ `STRING_AGG`, `RETURNING` ↔
`OUTPUT_CLAUSE`, `INSERT_REPLACE` ↔ `UPSERT`, and specific messages for
`SKIP_LOCKED`, `JSON`, `LATERAL`, `FULL_JOIN`.

See [Types](types.md), [Migrations](migrations.md) and
[Compatibility](compatibility.md).
