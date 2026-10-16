# Migration from Laravel

`OrmUniversal\Relational` is **Core without Laravel**: it does not import `Illuminate\*`.
Coexistence with Eloquent is provided by the adapter package
(`OrmUniversal\Laravel`) in **hybrid mode**: Eloquent keeps working and the
relational engine can be used incrementally over the same database.

The complete platform guide is at
[`docs/ude/MIGRATION_FROM_LARAVEL.md`](../../../docs/ude/MIGRATION_FROM_LARAVEL.md).

## What is hybrid and what is not

- Hybrid means both layers share connection and data; it does not mean the core
  knows about the framework.
- Heavy or reporting reads are migrated first to `recordset()`/`cursor()`.
- Destructive writes are migrated afterwards, with an explicit
  `DestructiveStatementPolicy`.
- There is no soft-delete magic: the engine does not inject `deleted_at IS NULL`;
  the condition is declared.

## Recommended steps

1. **Install without touching Eloquent**:
   `composer require roskiboy/orm-universal-laravel`. You can verify the engine with
   [Quickstart](quickstart.md) on an in-memory SQLite connection.
2. **Create the dialect and executor reusing the existing connection**:
   `DialectResolver::resolve('mysql', $serverVersion)` +
   `new QueryExecutor($pdoConnection, $dialect)`.
3. **Pick a read-only table** and migrate reporting queries to
   `QueryBuilder::table($executor, 'tabla')`, keeping Eloquent for CRUD.
4. **Compare results** manually between both paths before changing
   calls (the platform tests verify parity, see
   [Testing](testing.md)).
5. **Adopt hydration** as appropriate: `light()` for views,
   `entity(Clase::class)` if you already have an entity, `models($factory)` to
   keep your Laravel models (see [ORM](orm.md)).
6. **Stream where needed**: `cursor()`, `lazy()`, `chunk()` for
   large datasets (see [Recordsets](recordset.md)).
7. **Migrate relations** with `RelationDefinition` + `RelationBatchLoader` to
   eliminate N+1 (see [Relations](relations.md)).
8. **Migrate writes** using `insert()`/`update()`/`delete()`, `upsert()`,
   bulk with `insertMany()` and the appropriate destructive policy.
9. **Migrate migrations**: `SchemaOperation` + `SchemaBuilder` compile DDL without
   executing; compare with `SchemaDiffer` before applying (see
   [Migrations](migrations.md) and [Schema](schema.md)).
10. **Profile** with `InMemoryProfiler` or your PSR profiler and decide the next
    batch.

## Compatibility modes

The adapter documents the modes `legacy`, `compatibility`, `hybrid` (default),
`optimized`, `native` and `strict`. The `strict` mode is the only one that can make
unclassified legacy SQL fail; `hybrid` never breaks Eloquent.

## Guides by scenario

- [Small project](migration/small-project.md)
- [Medium project](migration/medium-project.md)
- [Large project](migration/large-project.md)
- [Legacy project](migration/legacy-project.md)
- [Multi-tenant](migration/multi-tenant.md)
- [Multi-database](migration/multi-database.md)
- [High traffic](migration/high-traffic.md)
- [Octane](migration/octane.md)
- [Queues](migration/queues.md)
- [Raw SQL heavy](migration/raw-sql-heavy.md)

## Coexistence rules

- Sharing the same connection avoids split transactions; use a single
  `PDO`/`ConnectionConfig` per database.
- Do not mix Eloquent's manual `BEGIN` with `PdoTransaction` on the same
  connection without coordination.
- Joins between different connections are blocked by
  `CrossDatabaseCapability`; do the join in the application.
- In long workers, `ConnectionManager::purge()` and `ContextScope::flush()`
  between requests.
