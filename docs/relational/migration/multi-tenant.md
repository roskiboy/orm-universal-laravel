# Migration: multi-tenant

The package does not impose a tenancy strategy. It provides explicit context and
named connections so the strategy is yours and auditable.

## Immutable context

```php
use OrmUniversal\Relational\Context\Context;

$context = Context::of([
    'tenant' => 'acme',
    'connection' => 'tenant_acme',
]);

$context->tenant();          // 'acme'
$context->connectionName();  // 'tenant_acme'
$withUser = $context->with('user_id', 42); // new instance
```

State is carried, not stored in globals. `ContextScope` stacks
contexts and `run()` guarantees restoring the previous one even on exception:

```php
use OrmUniversal\Relational\Context\ContextScope;

$scope = new ContextScope;

$result = $scope->run($context, function (Context $current) use ($executor): array {
    return \OrmUniversal\Relational\Builder\QueryBuilder::table($executor, 'orders')
        ->where('tenant', $current->tenant())
        ->get();
});
```

## Route connection per tenant

```php
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;
use OrmUniversal\Relational\Driver\ConnectionManager;

$manager = new ConnectionManager([
    'default' => new ConnectionConfig(driver: 'pgsql', database: 'shared'),
    'tenant_acme' => new ConnectionConfig(driver: 'pgsql', database: 'acme'),
]);

$connection = $manager->connection($context->connectionName() ?? 'default');
```

`ConnectionManager::add()`/`close()`/`purge()` allow rotating pools; new tenants
are created lazily on the first `connection()`.

## QueryContext

```php
use OrmUniversal\Relational\Context\QueryContext;

$queryContext = QueryContext::make(
    connection: 'tenant_acme',
    dialect: $dialect,
    tenant: 'acme',
    options: ['read_replica' => false],
);

$read = $queryContext->withOption('read_replica', true); // does not mutate the original
$read->tenant();       // 'acme'
$read->option('read_replica');
```

It is immutable and safe to share between workers. `ExecutionContext::make()`
adds transaction, hydration mode and `PerformanceMode`.

## Data isolation

- Explicit filter by column (`where('tenant', ...)`) or a separate database per
  connection; the engine does not inject filters for you.
- Caches: `QueryCache::key()` and `SchemaCache::key()` already include dialect and
  connection; do not reuse snapshots across tenants by accident.
- Relations: register them with `RelationRegistry` per model/table and pass the
  tenant in the queries.

## Long workers

In Octane/queues, `ContextScope::flush()` and `ConnectionManager::purge()` between
requests/jobs prevent tenant leaks:

```php
$scope->flush();
$manager->purge();
```

See [Octane](octane.md), [Queues](queues.md) and [Contexts](../architecture.md).
