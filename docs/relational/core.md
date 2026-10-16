# Core: structure and responsibilities

Root namespace: `OrmUniversal\Relational` (PSR-4 → `src/`). The package does not depend
on Laravel; the shared contracts live in `OrmUniversal\Contracts`.

```text
packages/relational/
├── composer.json          php ^8.3, ext-pdo, ext-json, OrmUniversal\Contracts
├── phpunit.xml.dist       "Relational" suite over tests/
├── phpstan.neon.dist      level 5, without treating PHPDoc as certainty
├── src/
│   ├── Ast/               query tree nodes and expressions
│   ├── Builder/           fluent QueryBuilder (converges to the AST)
│   ├── Business/          scopes, operations, rules and specifications
│   ├── Cache/             QueryCache and SchemaCache (PSR-16)
│   ├── Compiler/          SqlCompiler, CompileContext, capabilities
│   ├── Context/           immutable contexts and ContextScope
│   ├── Dialect/           AbstractDialect + 6 dialects and CapabilitySet
│   ├── Driver/            PdoDriver, PdoConnection, cursor, transaction
│   ├── Execution/         QueryExecutor, BatchPlanner, destructive policy
│   ├── Hydration/         Hydrator (zero/light/entity/factory)
│   ├── Migration/         SchemaOperation, DDL Compiler, SchemaBuilder
│   ├── Observability/     InMemoryProfiler
│   ├── Optimizer/         Normalizer, Validator, Optimizer, passes
│   ├── Plugin/            PluginRegistry
│   ├── Recordset/         materialized and streaming recordsets
│   ├── Relation/          metadata, batch loader, graph, N+1 detector
│   ├── Result/            Paginator
│   ├── Schema/            inspectors, snapshot, differ
│   ├── Support/           CrossDatabaseCapability
│   └── Type/              universal type system
└── tests/
    ├── Dialect/           multi-dialect golden SQL
    ├── Engine/            engine extensions without a server
    ├── Integration/       real in-memory SQLite
    └── Migration/         multi-dialect golden DDL
```

## Responsibilities per folder

### Ast

Representation of queries and expressions. Includes `Node`, `Expression`,
`SelectQuery`, `InsertQuery`, `UpdateQuery`, `DeleteQuery`, `RawQuery`,
`PredicateGroup`, `Join`, `Cte` and the expression nodes (`Column`, `Value`,
`Parameter`, `RawExpression`, `Comparison`, `LogicalExpression`, `Between`,
`InList`, `Exists`, `FunctionCall`, `Aggregate`, `CaseExpression`, `Subquery`,
`AliasedExpression`, `WindowExpression`, `JsonExpression`, `DateExpression`,
`StringExpression`, `NumericExpression`). See [AST](ast.md) and
[Expressions](expressions.md).

### Builder

`QueryBuilder`: the only fluent facade. It delegates to `SelectQuery` and exposes
execution, bulk operations, inspection and policies. See
[Query Engine](query-engine.md).

### Compiler

`SqlCompiler` translates any `QueryObjectInterface` into a `CompiledQuery`.
`CompileContext` accumulates bindings and applies `strictIdentifiers`.
`CapabilityAnalyzer`/`CapabilityNegotiator` compute and validate capabilities.

### Dialect

`AbstractDialect` defines conservative hooks (quoting, limits, locks, JSON,
aggregates) and delegates full compilation. Each vendor provides its dialect and
its matrix in `Dialect/Capabilities`. See [Dialects](dialects.md).

### Driver

PDO layer: connection, statement cache, forward-only cursor, transactions with
savepoints and multi-connection `ConnectionManager`. See [Drivers](drivers.md) and
[PDO](pdo.md).

### Execution

`QueryExecutor` compiles and executes; `BatchPlanner` splits bulk operations
according to `MAX_PLACEHOLDERS`; `DestructiveStatementPolicy` protects UPDATE/DELETE without
WHERE. See [Performance](performance.md) and [Security](security.md).

### Hydration, Recordset, Relation, Business

Hydration by modes ([ORM](orm.md)), result contracts
([Recordsets](recordset.md)), metadata and batch loading
([Relations](relations.md)) and scope/operation resolution
([Business Logic](business-logic.md)).

### Migration and Schema

Dialect DDL (`Migration\Compiler`, `SchemaBuilder`) and inspection/diff
(`SchemaInspectorFactory`, `SchemaDiffer`). See [Migrations](migrations.md) and
[Schema](schema.md).

### Type

Universal types and their per-engine aliases, with cast/serialize/deserialize.
See [Types](types.md).

### Cache, Plugin, Support, Observability, Result, Context

PSR-16 caches, extension registry, cross-database guards, bounded profiler,
paginator and immutable contexts. See [Extending](extending.md),
[Plugins](plugin-system.md) and [Performance](performance.md).
