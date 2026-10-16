# Performance

Honest note: this document describes **real mechanisms** of the package, not
benchmarks. The package does not publish performance figures; any number must be
measured on your workload with your data.

## BatchPlanner

Splits bulk operations according to the dialect's placeholder budget:

```php
use OrmUniversal\Relational\Execution\BatchPlanner;

$planner = new BatchPlanner($dialect, safetyFactor: 0.5);
$planner->maxParameters();
$planner->maxRowsPerBatch($columnsPerRow);
$planner->chunkRows($rows, $columnsPerRow);
$planner->chunkValues($values);
$planner->report();
```

Real formula: `maxRowsPerBatch = floor(maxParameters * safetyFactor / columns)`
with `safetyFactor = 0.5` by default. `maxParameters()` reads
`Capability::MAX_PLACEHOLDERS` with fallback `1000`:

| Engine | `MAX_PLACEHOLDERS` |
|---|---|
| MySQL / MariaDB / PostgreSQL / Oracle | 65535 |
| SQLite ≥ 3.32 | 32766 |
| SQLite < 3.32 | 999 |
| SQL Server | 2100 |

`QueryBuilder::insertMany()`, `upsertMany()`, `updateMany()` and `deleteMany()`
use the planner internally; `batchPlanner()` returns a ready instance.

## Fingerprints

`QueryFingerprint::fromSql($sql, $bindingCount, ?$structuralHash)` normalizes
literals, quoted strings, numbers and whitespace, and computes `sha256`. The same
query shape shares a fingerprint even if the values change.

- `QueryFingerprint::normalize()` is public.
- `equals()` compares hash + number of bindings.
- `(string) $fingerprint` returns the hash.

ASTs also generate a structural fingerprint (`SelectQuery::fingerprint()`,
etc.) to cache without SQL.

## Caches

### QueryCache

`OrmUniversal\Relational\Cache\QueryCache` uses PSR-16 (`CacheInterface`):

```php
use OrmUniversal\Relational\Cache\QueryCache;

$cache = new QueryCache($psr16, namespace: 'ude', ttl: 3600);
$key = $cache->key($dialect, 'compiled', (string) $fingerprint);
// ude:compiled:postgresql:18.0.0:<sha1>
$cache->put($key, $compiled);
$cache->has($key);
$cache->get($key);
$cache->forget($key);
```

The key includes **vendor + numeric version** so as not to leak SQL between
engines.

### SchemaCache

`SchemaCache::remember(string $key, callable(): SchemaSnapshot $resolver, ?int $ttl = null)`
caches snapshots with the key `namespace:schema:connection:dialect:schema`.
`forget()` requires explicit invalidation after migrations or connection changes.

## Statement cache and cursors

- `PdoConnection` caches up to 128 prepared statements by `xxh128` hash of the
  SQL (configurable or disableable with `statementCacheSize <= 0`).
- `PdoCursor` buffers rows already read so it does not re-execute the query.
- `StreamingRecordset` drains and caches as it is consumed.
- `LazyRecordset` paginates with `(chunkSize, page)` for bounded memory.

Recommended pattern for large tables:

```php
foreach ($builder->where('active', true)->cursor() as $row) { /* ... */ }
$builder->chunk(1000, fn (array $chunk, int $page) => $page < 10); // false stops
```

## Profiling

`InMemoryProfiler` stores at most 500 `QueryProfileEvent`s (FIFO) and summarizes
`queries`, `total_ms`, `slowest_ms` and the 10 most repeated fingerprints. It is bounded on
purpose: in production connect your PSR profiler via `ProfilerInterface`.

See [Optimizer](optimizer.md), [Drivers](drivers.md) and [Recordsets](recordset.md).
