# Migration: small project

A small project (one database, one or two developers, few tables) can
adopt the engine **without rewriting anything**: reads first, writes afterwards.

## Strategy

1. A single connection: reuse the existing `PDO`.
2. Migrate listings/reports first, which is where `recordset()` and `cursor()`
   bring clarity.
3. Leave CRUD in the framework if it already works; there is no obligation to migrate it.

## Minimal setup

```php
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;

$pdo = new PDO('sqlite:'.__DIR__.'/app.sqlite');
$connection = new PdoConnection($pdo, 'default');
$dialect = DialectResolver::resolve('sqlite', $connection->serverVersion());
$executor = new QueryExecutor($connection, $dialect);
```

## Typical case: listing with filters

```php
use OrmUniversal\Relational\Builder\QueryBuilder;

$invoices = QueryBuilder::table($executor, 'invoices')
    ->select('id', 'number', 'total', 'status')
    ->where('status', 'pending')
    ->whereBetween('total', 100, 5000)
    ->latest('created_at')
    ->limit(50)
    ->get();
```

Without extra hydration: `get()` returns arrays. For views that expect objects,
`->light()` returns `stdClass`.

## Report with streaming

If the report exceeds what you want to keep in memory:

```php
foreach (
    QueryBuilder::table($executor, 'invoices')
        ->where('status', 'pending')
        ->orderBy('id')
        ->cursor() as $row
) {
    fputcsv($handle, $row);
}
```

## Coexistence steps

- Keep your current migrations while you do not need multi-dialect.
- When you want portable DDL, start with `SchemaOperation::createTable()`
  only for new tables ([Migrations](../migrations.md)).
- Do not introduce `DestructiveStatementPolicy` in `DENY` all at once: `WARN` is the
  default and shows the problem without breaking ([Security](../security.md)).
- Verify each change with `->sql()` and `->bindings()` before executing.

## Checklist

- [ ] Single shared connection.
- [ ] Read queries migrated and compared with the previous ones.
- [ ] Critical writes with explicit `where()`.
- [ ] `composer install` + `vendor/bin/phpunit` if the project has tests
      ([Testing](../testing.md)).
