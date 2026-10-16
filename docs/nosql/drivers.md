# Drivers — OrmUniversal\NoSql

Three real implementations of `DocumentDriverInterface`. Each one declares its
`CapabilitySet` and fails with `DriverException` or `UnsupportedFeatureException` instead of
simulating capabilities.

## Summary

| Driver | Class | State | Requirements |
|---|---|---|---|
| InMemory | `InMemoryDriver` | Stable, reference | None |
| MongoDB | `MongoDbDriver` | Stable | `ext-mongodb` + `mongodb/mongodb ^2.4` |
| Redis | `RedisDriver` | Stable basic | `ext-redis` (phpredis 5+) |

## `InMemoryDriver`

- Constructor with no arguments (`new InMemoryDriver()`).
- `name()` returns `in-memory`.
- Declared capabilities: `NOSQL_JSON_DOCUMENTS`, `NOSQL_AGGREGATION`, `NOSQL_PIPELINE`,
  `NOSQL_TRANSACTIONS`.
- Ids: `_id`/`id` from the document or internal sequence `++$this->sequence`.
- `find()` supports `sort`, `skip`, and `limit` in `$options`.
- Update: `$set`, `$inc`, or plain replacement (removes `_id` from the replacement).
- `aggregate()` implements `$match`, `$sort`, `$skip`, `$limit`, `$project`, `$group`
  (`$sum`, `$avg`, `$min`, `$max`, `$count`, `$first`), and `$unwind`.
- Limitation: everything lives in process memory; there is no persistence or concurrency
  across workers.

## `MongoDbDriver`

```php
$driver = new MongoDbDriver(
    uri: 'mongodb://127.0.0.1:27017',
    database: 'app',
    options: [],
);
```

- `available()` checks `extension_loaded('mongodb')` and `class_exists(Client::class)`.
- Lazy client: the connection opens on first use; if the extension is missing it throws
  `DriverException` with installation instructions.
- Declared capabilities: JSON documents, aggregation, pipeline, transactions, change
  streams, TTL, partial indexes, geo, text search, time-series.
- `updateMany()` normalizes plain updates to `$set` and accepts `$set`, `$inc`,
  `$unset`.
- Honest limitations:
  - Vector Search is not declared: it is Atlas-exclusive and is not certified on self-managed
    servers.
  - The connection is cached for the lifetime of the object; there is no automatic reconnection.
  - `insertMany()` converts ObjectId to string or integer according to `__toString()`.

## `RedisDriver`

```php
$driver = new RedisDriver(
    host: '127.0.0.1',
    port: 6379,
    prefix: 'ude',
    password: null,
    database: 0,
);
```

- `available()` checks `extension_loaded('redis')`.
- Structure: each document as JSON in `prefix:col:<collection>:doc:<id>` and a set of
  ids in `prefix:col:<collection>:ids`.
- Declared capabilities: key-value, JSON documents, TTL, pub/sub, streams, Lua,
  persistence.
- Honest limitations:
  - **Does not filter on the server**: `find()`/`count()` load all documents with `mGet`
    and apply `FilterMatcher` in PHP.
  - `find()` ignores `$options` (`sort`, `skip`, `limit`).
  - `aggregate()` throws `UnsupportedFeatureException`; it does not simulate pipelines.
  - `updateMany()` only applies `$set` or plain update; it does not support `$inc` or
    advanced operators.
  - The tested line is Redis 5.0.14 (EOL); the modern 6.x–8.x capabilities are
    documented but not tested in this repository.

## Driver selection

```php
if (MongoDbDriver::available()) {
    $store->register('mongodb', new MongoDbDriver(...), default: true);
} else {
    $store->register('memory', new InMemoryDriver(), default: true);
}
```

Do not register a driver whose `available()` is false: the failure must be explicit at the
moment the collection is used.
