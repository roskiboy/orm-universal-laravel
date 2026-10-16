# Schema

Schema inspection lives in `OrmUniversal\Relational\Schema`. It reads real
metadata from the engine and normalizes it into value objects; **it never executes DDL**.

## SchemaInspectorFactory

```php
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

$inspector = SchemaInspectorFactory::for($dialect, $executor);
```

It returns the vendor's inspector (`mysql`/`mariadb` → `MySqlSchemaInspector`,
`postgresql` → `PostgreSqlSchemaInspector`, `sqlite` → `SqliteSchemaInspector`,
`sqlserver` → `SqlServerSchemaInspector`, `oracle` → `OracleSchemaInspector`).
With a vendor that has no registered inspector it throws `DialectException`.

## Inspector contract

`SchemaInspectorInterface` (implemented by `AbstractSchemaInspector`):

- `table(TableIdentifier $table): ?TableDefinition`
- `snapshot(?string $schema = null): SchemaSnapshot`
- `tables(?string $schema = null): list<string>`
- `columns(TableIdentifier $table): list<ColumnDefinition>`
- `indexes(TableIdentifier $table): list<IndexDefinition>`
- `foreignKeys(TableIdentifier $table): list<ForeignKeyDefinition>`

```php
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

$table = $inspector->table(TableIdentifier::fromString('orders'));
$table?->column('total');           // ?ColumnDefinition
$table?->toArray();

$snapshot = $inspector->snapshot();
$snapshot->tableNames();
$snapshot->table('orders');
$snapshot->toArray();
```

`AbstractSchemaInspector::table()` compares names case-insensitively
(`strcasecmp`) and builds a `TableDefinition` with columns, indexes and FKs.

## SchemaSnapshot

`OrmUniversal\Contracts\ValueObjects\SchemaSnapshot`:

```php
$snapshot = new SchemaSnapshot(
    tables: ['orders' => $tableDefinition],   // keyed by table name
    dialect: 'postgresql',
    serverVersion: '18.1',
    generatedAt: date('c'),
);

$snapshot->table('orders');   // ?TableDefinition
$snapshot->tableNames();      // list<string>
$snapshot->toArray();         // dialect, server_version, generated_at, tables
```

It is serializable and cacheable. `SchemaCache::remember()` stores it by
`(connection, dialect, schema)`. See [Performance](performance.md).

## SchemaDiffer

Compares two snapshots **without touching the database**:

```php
use OrmUniversal\Relational\Schema\SchemaDiffer;

$diff = (new SchemaDiffer)->diff($current, $desired);
// [
//   'tables_to_create' => [...],
//   'tables_to_drop'   => [...],
//   'tables_changed'   => [
//       'orders' => [
//           'columns_to_add' => [...],
//           'columns_to_drop' => [...],
//           'columns_changed' => [...],
//           'indexes_to_add' => [...],
//           'foreign_keys_to_add' => [...],
//       ],
//   ],
// ]
```

`diffTable(TableDefinition $current, TableDefinition $desired)` is public and
compares columns by type/nullable, detects missing columns and new indexes and FKs. It does not generate SQL: generation is the responsibility of
[Migrations](migrations.md).

## Inferred relations

`RelationResolver::belongsToMany()` and `RelationResolver::hasMany()` convert
`ForeignKeyDefinition` into `RelationMetadata` when the evidence is
unambiguous. See [Relations](relations.md).

## Recommended flow

1. `SchemaInspectorFactory::for($dialect, $executor)->snapshot()` — current picture.
2. Build the desired `SchemaSnapshot` with `TableDefinition`.
3. `SchemaDiffer::diff($current, $desired)` — side-effect-free plan.
4. Compile operations with `SchemaBuilder` and execute them with the security
   policy active. See [Migrations](migrations.md) and [Security](security.md).
