# Migration: high traffic

Site or API with many queries per second. The goal is to spend fewer
resources per request, not to rewrite the domain.

## Read without materializing

```php
use OrmUniversal\Relational\Builder\QueryBuilder;

$builder = QueryBuilder::table($executor, 'events')->where('processed', false);

foreach ($builder->cursor() as $row) {
    // streaming; PdoCursor buffers only what you are reading
}
```

`cursor()` reduces memory, but **PdoCursor keeps already-read rows in a buffer**;
if the set is really huge, process in batches and close:

```php
$builder->orderBy('id')->chunk(1000, function (array $chunk, int $page): bool {
    // process
    return $page < 100; // false stops
});
```

For stable pagination without deep `OFFSET`, use manual keyset with the last
id seen:

```php
$builder->where('id', '>', $lastId)->orderBy('id')->limit(1000)->get();
```

## Write in batches

`insertMany()`, `upsertMany()`, `updateMany()` and `deleteMany()` respect the
dialect's placeholder budget via `BatchPlanner`
(`safetyFactor = 0.5`). Do not send gigantic batches "by hand": the real limit varies
per engine (2100 on SQL Server, 32766 on modern SQLite, 65535 on the rest).

```php
$builder->upsertMany($rows, ['external_id'], ['status', 'updated_at']);
```

## Statement cache

`PdoConnection` caches statements by `hash('xxh128', $sql)` (128 by default).
Queries with stable SQL benefit; SQL generated with variable literals
degrades it. Always pass values through bindings.

## Application caches

- `QueryCache`: store `CompiledQuery` or normalized AST by fingerprint.
- `SchemaCache`: schema snapshots with explicit invalidation after migrating.
- `InMemoryProfiler::summary()` shows the most repeated fingerprints: candidates for
  caching or for N+1 fixes.

## Detecting N+1 in real traffic

`N1Detector` with `Telemetry` mode sends repeatedly loaded relations
to the logs:

```php
use OrmUniversal\Contracts\Enums\NPlusOneMode;
use OrmUniversal\Relational\Relation\N1Detector;

$detector = new N1Detector(NPlusOneMode::Telemetry, $logger, threshold: 3);
$detector->record('orders.items', parentCount: count($orders));
```

In CI use `Exception` to block regressions.

## Avoid `paginate()` on huge tables

`paginate()` runs a `count()` and then the `limit/offset`. With millions of
rows, prefer `cursor()` or keyset. `Paginator::hasMorePages()` can be deduced
without the total if your layer allows it.

## Checklist

- [ ] Large reads via `cursor()`/`chunk()`/keyset.
- [ ] Bulk writes via `*Many()` and `BatchPlanner`.
- [ ] Bindings always (never literals in the SQL).
- [ ] `QueryCache`/`SchemaCache` with invalidation.
- [ ] N+1 telemetered in production, blocked in CI.

See [Performance](../performance.md) and [Recordsets](../recordset.md).
