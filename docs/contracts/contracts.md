# Contracts — OrmUniversal\Contracts

Real inventory of `src/`. Everything marked `@api public` is a stable surface under SemVer.

## Interfaces

### Query and compilation

| Interface | Key methods |
|---|---|
| `QueryObjectInterface` | `toAst()`, `fingerprint()`, `requiredCapabilities()`, `toPlan()` |
| `AstNodeInterface` | `nodeType()`, `children()` |
| `ExpressionInterface` | extends `AstNodeInterface`; values travel as bindings |
| `CompilerInterface` | `compileQuery()`, `compileExpression()` |
| `DialectInterface` | `name()`, `vendor()`, `family()`, `version()`, `capabilities()`, `supports()`, `driverName()`, `quoteIdentifier()`, `compile()`, `compileExpression()`, `migrationCompiler()`, `limitations()` |
| `MigrationCompilerInterface` | `compile()`, `supports()` |
| `CapabilityNegotiatorInterface` | `requiredFor()`, `negotiate()` |
| `OptimizerPassInterface` | `name()`, `apply()` |

### Execution and connection

| Interface | Key methods |
|---|---|
| `DriverInterface` | `name()`, `connect()`, `supports()`, `requiredExtensions()` |
| `ConnectionInterface` | `select()`, `execute()`, `selectOne()`, `selectValue()`, `prepare()`, `transaction()`, `lastInsertId()`, `driverName()`, `serverVersion()`, `ping()`, `close()`, `rawPdo()` |
| `StatementInterface` | `execute()`, `fetch()`, `fetchAll()`, `fetchColumn()`, `rowCount()`, `close()` |
| `CursorInterface` | `getIterator()`, `close()` |
| `TransactionInterface` | `begin()`, `commit()`, `rollback()`, `supportsSavepoints()`, `savepoint()`, `rollbackTo()`, `releaseSavepoint()`, `state()`, `level()`, `isActive()` |
| `ContextInterface` | `get()`, `has()`, `all()`, `with()`, `without()`, `transaction()`, `tenant()`, `connectionName()` |

### Results, schema, and types

| Interface | Key methods |
|---|---|
| `RecordsetInterface` | `first()`, `last()`, `current()`, `next()`, `previous()`, `count()`, `exists()`, `isEmpty()`, `get()`, `has()`, `each()`, `map()`, `filter()`, `reduce()`, `chunk()`, `lazy()`, `cursor()`, `stream()`, `toArray()`, `toCollection()`, `paginate()` |
| `HydratorInterface` | `mode()`, `hydrate()`, `hydrateMany()` |
| `EntityInterface` | `attributes()`, `getAttribute()`, `setAttribute()`, `exists()` |
| `PaginatorInterface` | `items()`, `total()`, `perPage()`, `currentPage()`, `lastPage()`, `hasMorePages()`, `toArray()` |
| `SchemaInspectorInterface` | `tables()`, `table()`, `columns()`, `indexes()`, `foreignKeys()`, `snapshot()` |
| `SchemaBuilderInterface` | `create()`, `drop()`, `dropIfExists()`, `rename()`, `truncate()` |
| `SchemaOperationInterface` | `operationName()` |
| `TypeInterface` | `name()`, `kind()`, `databaseType()`, `phpType()`, `cast()`, `serialize()`, `deserialize()` |
| `TypeRegistryInterface` | `register()`, `has()`, `get()`, `resolve()`, `all()` |

### Relations, business, plugins, and observability

| Interface | Key methods |
|---|---|
| `RelationDefinitionInterface` | `name()`, `type()`, `relatedTable()`, `foreignKey()`, `localKey()`, `isToMany()`, `metadata()` |
| `RelationLoaderInterface` | `supports()`, `load()` |
| `BusinessRegistryInterface` | `registerScope()`, `registerSpecification()`, `registerOperation()`, `scope()`, `specification()`, `operation()`, `scopes()` |
| `QuerySpecificationInterface` | `apply()` |
| `PluginInterface` | `name()`, `register()`, `boot()` |
| `ProfilerInterface` | `record()`, `events()`, `reset()` |

## Enums (`src/Enums/`)

`AggregateFunction`, `ApiStability`, `ComparisonOperator`, `CompatibilityMode`,
`ConstraintType`, `DatabaseFamily`, `DialectVendor`, `HydrationMode`, `IndexType`,
`JoinType`, `LogicalOperator`, `NPlusOneMode`, `PerformanceMode`, `PortabilityClass`,
`RawType`, `RecordsetMode`, `SortDirection`, `StabilityLevel`, `TransactionState`,
`TypeKind`.

`CompatibilityMode` defines the Laravel adoption modes: `legacy`, `compatibility`,
`hybrid`, `optimized`, `native`, `strict` (`allowsLegacyApis()`).

## Value objects (`src/ValueObjects/`)

`TableIdentifier`, `ColumnIdentifier`, `SqlFragment`, `DialectVersion`, `CapabilitySet`,
`QueryFingerprint`, `CompiledQuery`, `CompiledExpression`, `Ordering`,
`ConnectionConfig`, `ColumnDefinition`, `IndexDefinition`, `ForeignKeyDefinition`,
`TableDefinition`, `SchemaSnapshot`, `QueryProfileEvent`, `ParameterBinding`,
`PortabilityReport`, `PerformanceReport`.

`SqlFragment` explicitly types raw SQL: `trusted()`, `parameterized()`,
`identifier()`, `value()`; `RawType` (`Trusted|Parameterized|Identifier|Value`)
accompanies each fragment. A fragment of type `Value` requires at least one binding.

## Exceptions (`src/Exceptions/`)

Base: `PersistenceException` (extends `RuntimeException`), with `context()` and
`withContext()`.

Derived: `BusinessOperationException`, `CompilationException`, `ConnectionException`,
`ContextException`, `DialectException`, `DriverException`, `HydrationException`,
`MigrationException`, `QueryException`, `RelationException`, `SecurityException`, and
`UnsupportedFeatureException` (extends `DialectException`).

`UnsupportedFeatureException` includes engine, version, feature, reason, and alternatives, and
offers a readable `report()`.

## Support

`Support\Capability` centralizes the canonical names of SQL and NoSQL capabilities
(`coreSql()`, `coreNoSql()`). `Support\ApiStatus` allows annotating lifecycle and
deprecations.
