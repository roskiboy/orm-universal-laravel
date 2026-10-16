# Troubleshooting — OrmUniversal\Laravel

## The provider is not registered

1. Confirm `composer dump-autoload`.
2. Review `extra.laravel.providers` in the package's `composer.json`.
3. If your Laravel does not use auto-discovery, register it manually in `config/app.php`.
4. `php artisan ude:doctor` fails with "Command not found": check that the provider
   is loaded and that you are running in console.

## `config/orm-universal.php` does not exist

This is normal: the provider does `mergeConfigFrom`. To have an editable file:

```bash
php artisan vendor:publish --tag=orm-universal-config
```

## The `OrmUniversal` facade does not resolve

- Verify the `OrmUniversal` alias → `OrmUniversal\Laravel\Facades\OrmUniversal`.
- The accessor is `Ude::class`; confirm with `app()->bound(Ude::class)`.
- After changing provider configuration, clear caches: `php artisan optimize:clear`.

## `ude:doctor` cannot connect

Review the given Laravel connection (`--connection=`), that PDO has the corresponding
driver (`php -m`), and that the database exists. The command prints the checks table
even if the connection fails and returns `FAILURE`.

## The `universal()`/`recordset()` macros do not exist

The provider registers them only if `Illuminate\Database\Eloquent\Builder` exists. If you
use a version without that class or a stub of your own, the macros are not installed; use
`OrmUniversal::table()` or the `HasUniversalQueries` trait.

## `strict` throws `QueryException` with raw SQL

This is the documented behavior: wrap the SQL in `SqlFragment::trusted()` or
`SqlFragment::parameterized()` to declare its nature, or switch modes.

## Odd behavior after several requests (Octane/queues)

Call `Ude::flush()` at the end of your own processes. In Octane the provider already
listens to the termination events; if you disable those listeners, manage the flush
manually.

## NoSQL `UnsupportedFeatureException`

The driver does not support the capability (for example, aggregation in Redis). Review
`OrmUniversal::nosql()->driver()->capabilities()` and the alternatives in the exception.

## `ude:schema:generate` does not write

Intentional behavior: add `--write`; to overwrite, also `--force`.
`ude:migrate:codemod` is always dry-run.

## The benchmark or the analyses take too long

Adjust `tooling.model_paths` and use smaller `--limit`/`--rows`. Reports are kept in
`storage/ude/`; clean them with `php artisan ude:cache:clear`.

## PHPStan errors due to Octane classes

They are deliberately ignored in `phpstan.neon.dist` (`class.notFound`): optional
integrations are resolved with `class_exists`.
