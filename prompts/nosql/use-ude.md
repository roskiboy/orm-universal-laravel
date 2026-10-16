# Prompt: use OrmUniversal\NoSql in an application

## Context

`OrmUniversal\NoSql` provides a document store with collections, filters, and pipelines, plus native
drivers for InMemory, MongoDB, and Redis. It does not depend on Laravel and does not convert documents to SQL.

## Goal

Ask an agent to:

1. Install `OrmUniversal\NoSql` (`composer require roskiboy/orm-universal-laravel`).
2. Create a `DocumentStore` and register drivers:

```php
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use OrmUniversal\NoSql\Driver\MongoDbDriver;
use OrmUniversal\NoSql\Driver\RedisDriver;

$store = new DocumentStore;
$store->register('in-memory', new InMemoryDriver, default: true);

if (MongoDbDriver::available()) {
    $store->register('mongodb', new MongoDbDriver('mongodb://127.0.0.1:27017', 'app'));
}

if (RedisDriver::available()) {
    $store->register('redis', new RedisDriver('127.0.0.1', 6379, 'app'));
}
```

3. Run CRUD with `$store->collection('orders')->insertMany/find/update/delete/count`.
4. Build filters with `Collection::where()` or `Filter::make()->eq()->gte()->or()`.
5. Aggregate with `Pipeline` (`match/group/sort/project/unwind/limit/skip`).
6. Negotiate capabilities before using features: `$driver->supports(Capability::NOSQL_AGGREGATION)`.

## Rules

- Do not assume aggregation in Redis: it throws `UnsupportedFeatureException` (catch and report).
- Use an explicit string/int `_id` when inserting into Mongo if `find()` will be used later
  (the self-generated `ObjectId` breaks `Document` in the current version).
- Do not pass `sort/skip/limit` options to `MongoDbDriver::find()` in this version
  (cursor without `sort()`): sort/paginate in the application or use `findOne()`.
- `Document` is immutable: to modify, use `updateMany` with `$set`/`$inc`.
- Do not use `$unset` with `InMemoryDriver` (only `$set`, `$inc`, and plain replacement).

## Deliverables

- Code with no Laravel dependency that runs with InMemory in tests.
- Contract tests with `InMemoryDriver` and conditional tests (skip) for Mongo/Redis.
- Document limits per driver in the consuming project's README.

## Verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Repo examples

`examples/basic.php` (CRUD), `intermediate.php` (filters), `advanced.php` (pipeline),
`expert.php` (real Mongo with honest skip), `extreme.php` (Redis + capabilities).
