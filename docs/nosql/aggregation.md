# Aggregation — OrmUniversal\NoSql

`Aggregation\Pipeline` represents native aggregation stages, independent of SQL.
The API builds it by chaining methods and `Collection::aggregate()` executes it.

## Construction

```php
use OrmUniversal\NoSql\Aggregation\Pipeline;

$pipeline = Pipeline::make()
    ->match(['status' => 'paid'])
    ->group(['_id' => '$customer', 'revenue' => ['$sum' => '$total']])
    ->sort(['revenue' => -1])
    ->limit(10);
```

## Available stages

| Method | Stage |
|---|---|
| `match(array $filter)` | `$match` |
| `group(array $spec)` | `$group` |
| `sort(array $fields)` | `$sort` (`1`/`-1`) |
| `limit(int $n)` | `$limit` (minimum 0) |
| `skip(int $n)` | `$skip` (minimum 0) |
| `project(array $spec)` | `$project` |
| `unwind(string $path)` | `$unwind` |
| `stage(array $stage)` | Arbitrary stage for native extensions |

`stages()` returns the list and `jsonSerialize()` allows transporting it as JSON.

## Execution

```php
$rows = $store->collection('orders')->aggregate($pipeline);
```

If there is a pending fluent filter, `Collection` prepends it as the first `$match`:

```php
$store->collection('orders')
    ->where('region', 'eu')
    ->aggregate(Pipeline::make()->group(['_id' => '$status', 'n' => ['$count' => 1]]));
```

`aggregate()` resets the filter after running.

## Real support per driver

| Driver | Aggregation | Detail |
|---|---|---|
| `InMemoryDriver` | Yes | `$match`, `$sort`, `$skip`, `$limit`, `$project`, `$group`, `$unwind` |
| `MongoDbDriver` | Yes | Native pipelines sent to the server |
| `RedisDriver` | No | Throws `UnsupportedFeatureException` |

Accumulators implemented by `InMemoryDriver` in `$group`: `$sum`, `$avg`, `$min`,
`$max`, `$count`, `$first`. Any other operator throws `InvalidArgumentException`.

## Honest limitations

- InMemory `$project` only includes existing fields (`true`); it does not compute expressions
  or exclusions.
- InMemory `$unwind` expects an array in the field; if it is not, it keeps the document.
- Stages not listed for InMemory throw `InvalidArgumentException`; in MongoDB they depend
  on the server.
- Redis does not simulate pipelines: the exception includes alternatives (aggregate in the application
  or use MongoDB).

## Good practices

- Keep the pipeline close to the driver: do not mix SQL semantics (GROUP BY, JOIN) with
  `$group`/`$lookup` stages without documenting it.
- Check `$driver->supports(Capability::NOSQL_AGGREGATION)` before building
  pipelines that will run against heterogeneous backends.
