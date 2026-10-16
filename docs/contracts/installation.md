# Installation — OrmUniversal\Contracts

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.3` |
| `psr/log` | `^3.0` |
| `psr/simple-cache` | `^3.0` |
| Composer | 2.x |

`contracts` is framework-agnostic: it does not require Laravel or any PDO extension. It only
declares stable interfaces, enums, value objects, and exceptions.

## Installing in an application

```bash
composer require roskiboy/orm-universal-laravel
```

Verify that the `OrmUniversal\Contracts\` namespace is autoloaded:

```bash
php -r "require 'vendor/autoload.php'; var_dump(interface_exists(OrmUniversal\\Contracts\\DialectInterface::class));"
```

## Installing from the monorepo (path repository)

If you work on the monorepo, register the local package in the root `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "packages/contracts" }
  ],
  "require": {
    "roskiboy/orm-universal-laravel": "^0.7"
  }
}
```

```bash
composer update roskiboy/orm-universal-laravel
```

## Package development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

The package is analyzed with PHPStan level 6 (`phpstan.neon.dist`, `treatPhpDocTypesAsCertain: false`).

## Relationship with the other packages

```text
contracts  ← relational
contracts  ← nosql
contracts  ← laravel (transitively relational and nosql)
```

The arrow is unidirectional: `contracts` never imports `Illuminate\*` or engine
implementations. If you are looking for a runnable engine, install `OrmUniversal\Relational` or
`OrmUniversal\NoSql`; if you use Laravel, `OrmUniversal\Laravel`.

## Verification

- `composer validate` must pass with no errors.
- `vendor/bin/phpunit` runs the contracts suite (value objects).
- The public API is marked with `@api public` in the source code.
