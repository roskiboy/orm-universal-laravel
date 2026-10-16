---
name: ude-nosql
description: NoSQL engine of OrmUniversal (OrmUniversal\NoSql) - document store, collections, filters, and aggregation pipelines with InMemory, MongoDB, and Redis drivers. Use when working with document data, aggregation pipelines, driver capabilities, or NoSQL without Laravel. Triggers: "OrmUniversal\NoSql", "DocumentStore", "InMemoryDriver", "MongoDbDriver", "RedisDriver", "Pipeline", "aggregate".
---

# UDE NoSQL (OrmUniversal\NoSql)

## When to use this package

- Persist JSON documents without artificially converting them to SQL.
- Native aggregations (`$match`, `$group`, `$sort`, `$project`, `$unwind`, `$limit`, `$skip`).
- Work with MongoDB or Redis while keeping each backend's own semantics.
- Contract tests with `InMemoryDriver` (no external server).

Dependency: `OrmUniversal\Contracts`. The Mongo/Redis drivers are optional
(`MongoDbDriver::available()` requires `ext-mongodb` + `mongodb/mongodb`; `RedisDriver::available()`
requires `ext-redis`).

## Key APIs (real, verified names)

- `NoSql\DocumentStore`: `register($name, $driver, $default = false)`, `driver()`,
  `collection($name, $driver = null)`, `drivers()`, `defaultDriver()`.
- `NoSql\Collection`: `where($field, $operator = null, $value = null)` (operators `eq/ne/gt/gte/lt/lte/in/exists/regex`
  or the symbols `= != > >= < <=`), `insert()`, `insertMany()`, `find()`, `first()`,
  `update()`, `delete()`, `count()`, `aggregate(?Pipeline)`, `each()`, `drop()`.
- `NoSql\Filter`: `make/eq/neq/gt/gte/lt/lte/in/exists/regex/and/or`; `toArray()`.
- `NoSql\Aggregation\Pipeline`: `make()->match()->group()->sort()->limit()->skip()->project()->unwind()->stage()`.
- `NoSql\Document`: `get()`, `has()`, `toArray()`, `__get`, `ArrayAccess`, immutable.
- Drivers: `Driver\InMemoryDriver`, `Driver\MongoDbDriver`, `Driver\RedisDriver`,
  interface `Driver\DocumentDriverInterface` (`insertMany/find/findOne/updateMany/deleteMany/count/aggregate/drop/collections/supports`).

## Recommended patterns

### InMemory: contract reference, ideal for tests

```php
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;

$store = new DocumentStore;
$store->register('memory', new InMemoryDriver, default: true);

$orders = $store->collection('orders');
$orders->insertMany([
    ['customer' => 'ana', 'total' => 120, 'status' => 'paid'],
    ['customer' => 'bob', 'total' => 80, 'status' => 'pending'],
]);

$paid = $orders->where('status', 'paid')->find();   // list<Document>
$paid[0]->get('customer');
```

### Aggregation pipeline (engine-neutral)

```php
use OrmUniversal\NoSql\Aggregation\Pipeline;

$revenue = $store->collection('orders')->aggregate(
    Pipeline::make()
        ->match(['status' => 'paid'])
        ->group(['_id' => '$customer', 'revenue' => ['$sum' => '$total'], 'count' => ['$count' => 1]])
        ->sort(['revenue' => -1]),
);
```

The `group` accumulators supported by `InMemoryDriver`: `$sum`, `$avg`, `$min`, `$max`, `$count`, `$first`.
A pending `where()` is prepended as `$match` to the pipeline.

### Negotiate capabilities per driver

```php
use OrmUniversal\Contracts\Support\Capability;

if ($driver->supports(Capability::NOSQL_AGGREGATION)) {
    $driver->aggregate('orders', $pipeline);
}
```

`RedisDriver` declares key-value, JSON documents, TTL, pub/sub, streams, Lua, and persistence,
but **not** aggregation: it throws `UnsupportedFeatureException` with alternatives (it does not simulate
pipelines). `MongoDbDriver` runs the server's native pipeline.

### Real MongoDB (with honest fallback)

```php
use OrmUniversal\NoSql\Driver\MongoDbDriver;

if (! MongoDbDriver::available()) {
    echo "SKIP: ext-mongodb + mongodb/mongodb not available\n";
} else {
    $mongo = new MongoDbDriver('mongodb://127.0.0.1:27017', 'app');
    $mongo->insertMany('orders', [['_id' => 'o1', 'customer' => 'ana', 'total' => 120]]);
    $one = $mongo->findOne('orders', ['customer' => 'ana']); // array
}
```

### Real Redis (key-value + JSON documents)

```php
use OrmUniversal\NoSql\Driver\RedisDriver;

if (RedisDriver::available()) {
    $redis = new RedisDriver('127.0.0.1', 6379, 'ude');
    $redis->insertMany('sessions', [['_id' => 's1', 'user' => 'ana']]);
    $redis->updateMany('sessions', ['user' => 'ana'], ['$set' => ['ttl' => 3600]]);
}
```

## Common mistakes

- Forgetting `available()` and getting a `DriverException` for a missing extension/client.
- Calling `aggregate()` on Redis: expected exception; catch
  `UnsupportedFeatureException` and show `report()`.
- Reusing a `Collection` after `first/update/delete/aggregate`: the pending filter
  is reset in those operations, not in `find/count`.
- Chaining `$collection->filter()->gte('age', 25)->count()`: `filter()` returns `Filter`, not
  the collection. Configure the filter and then call the collection method separately.
- Combining operators for the **same field** with `Filter::gte()->lt()`: `and()` uses `array_merge`
  and the second operator overwrites the first. For ranges use a single document:
  `and(['age' => ['$gte' => 25, '$lt' => 45]])`.
- Expecting `eq()->or(...)` to be a global OR: `$or` is evaluated together with the rest of the fields
  (AND). For a pure OR use `Filter::make()->or(sub1, sub2)` with no other keys.
- `$group` with `['$count' => 1]` only works in `InMemoryDriver`; in MongoDB use
  `['$sum' => 1]`.
- `MongoDbDriver::insertMany()` casts non-object ids to `int`: a string `_id` `'o1'` comes back
  as `0`. Use an int `_id` (or BSON ObjectId) and avoid trusting the returned value for strings.
- Passing `$unset` to `InMemoryDriver::updateMany`: it only supports `$set`, `$inc`, or plain replacement.
- Using Mongo's `find()` with `sort/skip/limit` options in this version: the returned cursor
  (`MongoDB\Driver\CursorInterface`) does not expose `sort()`; sort/paginate in the application.
- Inserting into Mongo with a self-generated `_id` (BSON `ObjectId`) and then using `find()`: `Document`
  types `id` as `int|string|null` and fails; use a string/int `_id` or `findOne()` (array).
- Assuming Redis filters on the server: the driver evaluates the filters in PHP over the
  JSON documents (documented as a limitation).

## Honest limits

- Redis **does not** have aggregation; the pipelines belong to Mongo/InMemory.
- `InMemoryDriver` is the reference implementation (non-persistent, single process).
- MongoDB Vector Search is Atlas-only and is **not** declared.
- `MongoDbDriver::find()` with `$options` (sort/skip/limit) is broken against
  mongodb/mongodb 2.4 (cursor without `sort()`); verified in the monorepo.
- The driver contract does not expose transactions: `InMemoryDriver` declares the capability
  `supportsTransactions`, but `DocumentDriverInterface` has no transactional methods.
- `Document` is immutable: `offsetSet/offsetUnset` throw `LogicException`.
