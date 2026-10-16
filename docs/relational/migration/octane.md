# Migration: Octane / long-lived workers

Under Octane, RoadRunner or Swoole the process does not die between requests. Any
mutable state can leak into the next request. The package is designed
for this: immutable contexts and explicit cleanup.

## Golden rules

1. Do not store `PDO`, `QueryExecutor` or `QueryBuilder` in static properties
   with per-request state.
2. At the end of each request: `ContextScope::flush()` and
   `ConnectionManager::purge()`.
3. Detect hanging transactions with `PdoTransaction::state()`/`isActive()`.
4. Reset profiler counters (`InMemoryProfiler::reset()`).

## Per-request context

```php
use OrmUniversal\Relational\Context\Context;
use OrmUniversal\Relational\Context\ContextScope;
use OrmUniversal\Relational\Context\RequestContext;

$scope = new ContextScope;

$request = RequestContext::generate();

$result = $scope->run(
    Context::of([
        'tenant' => $tenant,
        'connection' => 'tenant_'.$tenant,
        'request_id' => $request->requestId(),
    ]),
    fn ($context) => $this->handle($context),
);

$scope->flush(); // mandatory at the end of the request
```

`ContextScope::depth()` allows verifying that no contexts were left stacked.

## Connections

```php
$manager->close('tenant_acme'); // returns the connection to the pool/closes it
$manager->purge();              // closes all live connections
```

`PdoConnection::ping()` validates that the connection responds (useful after long
idle periods); if `isClosed()` is `true`, it must be reopened with the manager.

## Leaked transactions

```php
use OrmUniversal\Relational\Context\TransactionContext;

$tx = $executor->transaction();
$tx->begin();

try {
    // work
    $tx->commit();
} catch (\Throwable $e) {
    if ($tx->isActive()) {
        $tx->rollBack();
    }
    throw $e;
}
```

`PdoTransaction` keeps an explicit `state()` (`None`, `Active`, `Committed`,
`RolledBack`, `Failed`), which allows auditing leaks:
`TransactionContext::for($tx)->toArray()`.

## Profiler and caches

- `InMemoryProfiler` stores up to 500 events; call `reset()` between requests if
  you use it for diagnosis.
- `QueryCache`/`SchemaCache` use PSR-16: pool persistence is yours.
- Avoid `static` for `MethodCache`; live it per request or per model.

## Verification

- Run two consecutive requests with different tenants and check that
  `ContextScope::current()` does not carry over the previous one.
- Run the full suite with the worker running:
  `vendor/bin/phpunit` ([Testing](../testing.md)).
- Use the profiler's `summary()` to detect anomalous accumulation.

See [Contexts](../architecture.md), [Drivers](../drivers.md) and
[Multi-tenant](multi-tenant.md).
