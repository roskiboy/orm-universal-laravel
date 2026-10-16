# Migration: multi-database

Several databases or engines in the same application (for example, OLTP on PostgreSQL and
reporting on MySQL). The package supports multiple connections, but **does not fake**
portability between them.

## One connection, one dialect, one executor

```php
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\ConnectionManager;
use OrmUniversal\Relational\Execution\QueryExecutor;

$manager = new ConnectionManager([
    'oltp' => new ConnectionConfig(driver: 'pgsql', host: 'db', database: 'app', username: 'app', password: 'secret'),
    'reports' => new ConnectionConfig(driver: 'mysql', host: 'db', database: 'reports', username: 'ro', password: 'secret'),
]);

$executors = [];

foreach ($manager->names() as $name) {
    $connection = $manager->connection($name);
    $dialect = DialectResolver::resolve($connection->driverName(), $connection->serverVersion());
    $executors[$name] = new QueryExecutor($connection, $dialect);
}

$orders = \OrmUniversal\Relational\Builder\QueryBuilder::table($executors['oltp'], 'orders');
```

Each `QueryExecutor` exposes `dialect()` and `capabilities()`, so you can compile
the same query with each engine and compare:

```php
$query = \OrmUniversal\Relational\Ast\SelectQuery::make('orders')->select('id')->limit(10);

echo $executors['oltp']->compile($query)->sql;
echo $executors['reports']->compile($query)->sql;
```

## Joins between connections: forbidden

`CrossDatabaseCapability` blocks the cross join because it is not portable:

```php
use OrmUniversal\Relational\Support\CrossDatabaseCapability;

(new CrossDatabaseCapability)->assertJoinAllowed('oltp', 'reports');
// UnsupportedFeatureException with alternatives
```

Documented alternatives: join in the application, ETL/materialized view or the
engine's federated capability if it exists. See [Security](../security.md).

## Transactions

Each connection has its own `PdoTransaction` (`$executor->transaction()`).
There is no distributed transaction. Safe patterns:

- **Saga/outbox**: write on one connection, publish an event, confirm the other
  with retries and idempotency.
- **Single writer**: concentrate writes on the primary connection and treat
  the secondary as a replica/derived.

## Caches and schema

- `QueryCache::key($dialect, $purpose, $fingerprint)` includes vendor and version:
  safe across engines.
- `SchemaCache::key($connection, $dialect, $schema)` distinguishes connection: do not
  reuse snapshots.
- Inspect each database separately with `SchemaInspectorFactory::for()`
  ([Schema](../schema.md)).

## Reporting reads

```php
foreach ($executors['reports']->cursor($reportQuery) as $row) {
    // streaming from the reporting database
}
```

`cursor()` comes from `QueryExecutor::cursor()`; `QueryBuilder::cursor()` is the
shortcut when you use the facade.

## Checklist

- [ ] One `QueryExecutor` per connection, with its detected dialect.
- [ ] Zero cross joins; unions in the application.
- [ ] Transactions coordinated by saga/outbox.
- [ ] Caches and snapshots separated per connection.
- [ ] Capabilities compared before assuming a feature in both engines.
