# Runnable examples: OrmUniversal\NoSql

`InMemoryDriver` is 100% runnable with no server. The MongoDB and Redis examples are
**skipped with a notice and exit 0** when `MongoDbDriver::available()` / `RedisDriver::available()`
is `false`, or when the server does not respond.

## Requirements

- PHP 8.3+ with `ext-json`.
- Autoload with fallback (package → monorepo):

```php
$packageAutoload = __DIR__.'/../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;
```

- Optional for real examples: `ext-mongodb` + `mongodb/mongodb` and a MongoDB server;
  `ext-redis` (phpredis) and a Redis server.

```bash
composer install   # package dependencies
# or the root vendor/ of the tms monorepo
```

## Execution

```bash
php examples/run.php     # runs the 5 and reports (skips count as success)
php examples/basic.php   # individual; exit 0 success/skip, exit 1 failure
```

## Files

| File | Contents |
|---|---|
| `basic.php` | `DocumentStore` + `InMemoryDriver`: CRUD, `Document`, ids. |
| `intermediate.php` | Filters: operators, `Filter::or`, dot notation, regex, `$set`/`$inc`. |
| `advanced.php` | Pipeline: `match/group/sort/project/unwind/limit/skip`, `each()`, `drop()`. |
| `expert.php` | Real MongoDB (if available): CRUD, `findOne`, native pipeline, capabilities. |
| `extreme.php` | Real Redis (if available): key-value + JSON, TTL capabilities, and unsupported aggregation reported. |

## Verified limits (honest)

- `MongoDbDriver::find()` with `sort/skip/limit` options fails against mongodb/mongodb 2.4
  (the cursor does not expose `sort()`): the examples sort/paginate in PHP.
- Mongo `find()` with a BSON `ObjectId` `_id` breaks `Document` (id typed `int|string|null`):
  the examples use a string `_id`.
- `RedisDriver` does not implement aggregation: it throws `UnsupportedFeatureException` with
  alternatives (it is caught and `report()` is shown).
- `InMemoryDriver::updateMany` supports `$set`, `$inc`, or plain replacement (no `$unset`).
