# Installation — OrmUniversal\NoSql

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.3` |
| `ext-json` | required |
| `OrmUniversal\Contracts` | `^0.1` |
| Composer | 2.x |

The package does not depend on Laravel. Remote drivers are optional and are detected at
runtime with `MongoDbDriver::available()` and `RedisDriver::available()`.

## Base installation

```bash
composer require roskiboy/orm-universal-laravel
```

The `InMemoryDriver` driver works without additional extensions and serves as the
reference implementation.

## Optional drivers

### MongoDB

Requires `ext-mongodb` and the high-level library:

```bash
composer require mongodb/mongodb:^2.4
```

Check:

```bash
php -r "var_dump(extension_loaded('mongodb'));"
```

### Redis

Requires the `ext-redis` extension (phpredis):

```bash
php -r "var_dump(extension_loaded('redis'));"
```

`composer.json` suggests `predis/predis` as an alternative, but the delivered driver uses
`\Redis` (phpredis).

## Installing from the monorepo (path repository)

```json
{
  "repositories": [
    { "type": "path", "url": "packages/nosql" }
  ],
  "require": {
    "roskiboy/orm-universal-laravel": "^0.7"
  }
}
```

```bash
composer update roskiboy/orm-universal-laravel
```

## Verification

```bash
composer validate
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

The suite uses `InMemoryDriver` always; the MongoDB and Redis tests are skipped
automatically if the server or the extension is not available.
