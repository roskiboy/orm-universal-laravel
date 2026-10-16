# Testing — OrmUniversal\NoSql

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Configuration

`phpunit.xml.dist`:

- `NoSql` suite over `tests/`, bootstrap `vendor/autoload.php`.
- `failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`.
- Integration variables: `UDE_MONGODB_URI` (`mongodb://127.0.0.1:27017`),
  `UDE_MONGODB_DATABASE` (`ude_test`), `UDE_REDIS_HOST` (`127.0.0.1`),
  `UDE_REDIS_PORT` (`6379`).

`phpstan.neon.dist`: level 5 over `src/`, with `class.notFound` ignored because the
optional backends are resolved with `class_exists`.

## Integration strategy

| Backend | When it runs | How it is skipped |
|---|---|---|
| InMemory | Always | Not applicable |
| MongoDB | `MongoDbDriver::available()` and reachable server | `markTestSkipped` |
| Redis | `RedisDriver::available()` and successful connection | `markTestSkipped` |

The real tests use the servers in the table above; if they are not available, the suite stays
green without them. Do not turn an integration skip into a false positive.

## Existing suite

| File | Coverage |
|---|---|
| `tests/Driver/DocumentStoreTest.php` | Registration, default, `DriverException` |
| `tests/Driver/InMemoryDriverTest.php` | CRUD, filters, aggregation, and pipeline |

## When adding tests

- Use `InMemoryDriver` as the semantics reference for CRUD and filters.
- Test every `FilterMatcher` operator you touch, including `$or`, `$and`, dot
  notation, and `$size`.
- For aggregation, cover `$group` of all implemented accumulators and an invalid
  stage that must throw `InvalidArgumentException`.
- For remote drivers, add a `capabilities()` test consistent with `supports()` and
  a missing-capability test (`UnsupportedFeatureException`).
- Do not leave data on shared servers: clean collections with `drop()`.

## Acceptance criteria

`vendor/bin/phpunit` and `vendor/bin/phpstan analyse` green, `composer validate` with no
errors, and `vendor/bin/pint` with no pending changes.
