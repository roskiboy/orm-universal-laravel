# Installation — orm-universal/laravel

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.3` |
| Laravel (`illuminate/*`) | `^12.0 \|\| ^13.0` |
| `orm-universal/contracts` | `^0.1` (dependency) |
| `orm-universal/relational` | `^0.1` (dependency) |
| `orm-universal/nosql` | `^0.1` (dependency) |
| Composer | 2.x |

The package includes auto-discovery of the service provider and the facade alias.

## Installation

```bash
composer require orm-universal/laravel
php artisan ude:install
```

`ude:install` publishes the configuration and runs `ude:doctor` to diagnose environment,
extensions, connection, dialect, and capabilities.

## Publishing the configuration

Publishing is done with the `orm-universal-config` tag:

```bash
php artisan vendor:publish --tag=orm-universal-config
```

Generates `config/orm-universal.php`. In the service provider the configuration is merged
with `mergeConfigFrom`, so the default values exist even if you do not publish it.

## Manual registration (without auto-discovery)

```php
// config/app.php
'providers' => [
    OrmUniversal\Laravel\OrmUniversalServiceProvider::class,
],
'aliases' => [
    'OrmUniversal' => OrmUniversal\Laravel\Facades\OrmUniversal::class,
],
```

## Installing from the monorepo (path repository)

```json
{
  "repositories": [
    { "type": "path", "url": "packages/contracts" },
    { "type": "path", "url": "packages/relational" },
    { "type": "path", "url": "packages/nosql" },
    { "type": "path", "url": "packages/laravel" }
  ],
  "require": {
    "orm-universal/laravel": "^0.1"
  }
}
```

## Optional drivers

- MongoDB: `ext-mongodb` + `mongodb/mongodb:^2.4`, enabled with
  `UDE_MONGODB_ENABLED=true`.
- Redis: `ext-redis`, enabled with `UDE_REDIS_ENABLED=true` (`false` by default).

`InMemoryDriver` is registered by default (`UDE_IN_MEMORY_ENABLED=true`).

## Verification

```bash
php artisan ude:doctor
php artisan ude:compatibility
composer validate
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
