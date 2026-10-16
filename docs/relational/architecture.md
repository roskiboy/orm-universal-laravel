# Architecture

`OrmUniversal\Relational` implements a one-way, explicit pipeline:

```
QueryBuilder / OO API
        │
        ▼
   AST (OrmUniversal\Relational\Ast)
        │
        ▼
 CapabilityNegotiator (capabilities negotiated per vendor + version)
        │
        ▼
     SqlCompiler (quoted identifiers, values as bindings)
        │
        ▼
  CompiledQuery (sql + bindings + fingerprint + capabilities)
        │
        ▼
 QueryExecutor / PdoConnection (PDO, prepared statements)
        │
        ▼
 PdoCursor / rows
        │
        ▼
 Hydrator → Recordset (Array, Streaming, Cursor, ...)
```

## Core-without-Laravel rule

The package is **Core without Laravel**: it does not import `Illuminate\*` and does not
require `OrmUniversal\Laravel` to work. This is reflected in:

- `composer.json` only requires `php`, `ext-pdo`, `ext-json` and
  `OrmUniversal\Contracts`.
- Hydration of framework models is done with a `callable` provided by the
  adapter (`Hydrator::factory()`, `QueryBuilder::models()`,
  `EloquentRecordset::fromRows()`).
- Contexts and business resolution are explicit; there is no mandatory service
  container.

The shared contracts live in `OrmUniversal\Contracts`
(`QueryObjectInterface`, `ConnectionInterface`, `RecordsetInterface`,
`CapabilitySet`, `CompiledQuery`, exceptions...).

## Pipeline stages

1. **AST construction.** `SelectQuery`, `InsertQuery`, `UpdateQuery`,
   `DeleteQuery` and `RawQuery` implement `QueryObjectInterface`. The OO API
   (`Expression::eq()`, `Column::make()`) and `QueryBuilder` produce the same
   nodes.
2. **Capability analysis and negotiation.** `CapabilityAnalyzer::forNode()`
   walks `children()` and detects features; `CapabilityNegotiator::negotiate()`
   checks them against `CapabilitySet` and throws
   `UnsupportedFeatureException` (with `report()` and alternatives) before
   compiling. Accepted equivalences exist: `GROUP_CONCAT` ↔ `STRING_AGG`,
   `RETURNING` ↔ `OUTPUT_CLAUSE`, `INSERT_REPLACE` ↔ `UPSERT`.
3. **Compilation.** `SqlCompiler::compileQuery()` emits SQL with identifiers
   quoted by the dialect (`AbstractDialect::quoteIdentifier()`), values
   always as `?` and bindings ordered in `CompileContext`. `RawExpression`
   must declare its `RawType`. The result is a `CompiledQuery`.
4. **Execution.** `QueryExecutor` resolves `ParameterPlaceholder` with
   `withParameters()` and delegates to `ConnectionInterface`: `select()`,
   `selectOne()`, `selectValue()` or `execute()`. `PdoConnection` prepares and
   caches statements and maps bindings to PDO types.
5. **Reading.** `PdoCursor` iterates rows lazily and caches them while
   they are consumed. The recordsets (`StreamingRecordset`, `CursorRecordset`,
   `LazyRecordset`) wrap the cursor; the materialized ones
   (`ArrayRecordset` and derivatives) already work with hydrated values.
6. **Hydration.** `Hydrator` converts each row according to the mode: `zero`
   (array), `light` (`stdClass`), `entity` (class with `EntityInterface` or
   properties) or `factory` (`HydrationMode::Eloquent`).

## Design principles

- **Values never interpolated**: `CompileContext::bind()` is the only path from
  a value to SQL.
- **SQL predictability**: the compiler flattens `AND`/`OR` groups of the same
  operator to keep golden SQL stable.
- **No silent magic**: ambiguity throws an exception (for example, a business
  scope defined in two models), and unsupported features
  report engine, version, reason and alternatives.
- **Explicit state**: `Context`, `ContextScope`, `QueryContext`,
  `ExecutionContext` are immutable and Octane-safe; `flush()` is manual.
- **Intact semantics**: the optimizer passes (`DuplicatePredicatePass`,
  `BooleanSimplificationPass`, `ConstantFoldingPass`) never change results.

## Related modules

- [Core](core.md): folder structure and responsibilities.
- [Query Engine](query-engine.md), [AST](ast.md), [Expressions](expressions.md).
- [Dialects](dialects.md), [Drivers](drivers.md), [PDO](pdo.md).
- [Recordsets](recordset.md), [ORM](orm.md), [Relations](relations.md).
- [Optimizer](optimizer.md), [Performance](performance.md), [Security](security.md).
