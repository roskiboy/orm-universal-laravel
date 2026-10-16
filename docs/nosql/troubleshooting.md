# Troubleshooting — OrmUniversal\NoSql

## `DriverException: The MongoDB driver requires ext-mongodb and mongodb/mongodb`

Install the extension and the library:

```bash
pecl install mongodb          # or enable extension=mongodb in php.ini
composer require mongodb/mongodb:^2.4
php -m | findstr mongodb
```

The connection is lazy: the error appears when running the first operation, not when
building `MongoDbDriver`.

## `DriverException: The Redis driver requires the phpredis extension (ext-redis)`

Install/enable `ext-redis` and verify with `php -r "var_dump(extension_loaded('redis'));"`.
`predis/predis` is listed in `suggest`, but the driver uses the `\Redis` class.

## `DriverException: Unknown NoSQL driver [x]`

The name is not registered in `DocumentStore`. Check `drivers()` and register the driver
before calling `collection()`. In Laravel, check `nosql.drivers` in
`config/orm-universal.php` and that the extension is available.

## `DriverException: No NoSQL driver registered`

No driver registered and no explicit name passed. Register one with
`$store->register('memory', new InMemoryDriver(), default: true);`.

## `UnsupportedFeatureException` when aggregating in Redis

This is the documented behavior: Redis has no pipelines. Alternatives from the exception
itself: aggregate in the application or use MongoDB. Not catchable to fabricate a false
result.

## `InvalidArgumentException: Unsupported aggregation stage`

The stage is not implemented in `InMemoryDriver`. Supported stages: `$match`, `$sort`,
`$skip`, `$limit`, `$project`, `$group`, `$unwind`. In MongoDB it depends on the server.

## `InvalidArgumentException: Unsupported NoSQL operator`

`FilterMatcher` only supports `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte`, `$in`, `$nin`,
`$exists`, `$regex`, `$size`, and `$or`/`$and` composition. Native MongoDB
operators are passed to the server, but they are not evaluated in InMemory/Redis.

## Redis returns unexpected results

`RedisDriver::find()` ignores `sort`, `skip`, and `limit` and filters in PHP. Sort and paginate in
the application, or use another driver for those queries.

## `json_encode` fails on insert

Redis serializes with `JSON_THROW_ON_ERROR`: documents must be JSON-serializable
(no resources or objects). Normalize to arrays/scalar values.

## Integration tests do not run

This is expected without a server: the suite skips them. To force them, start MongoDB/Redis on
the hosts in `phpunit.xml.dist` and confirm that the extensions are loaded.
