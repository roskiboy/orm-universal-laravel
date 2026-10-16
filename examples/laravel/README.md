# Integration examples: OrmUniversal\Laravel

> **These files do NOT run standalone.** They are integration fragments to copy
> into a Laravel app with `OrmUniversal\Laravel` installed (provider with
> auto-discovery). `run.php` only prints this requirement and exits with code 0.

## Requirements to actually use them

```bash
composer require roskiboy/orm-universal-laravel
php artisan ude:install
```

- Laravel 12.x / 13.x.
- `OrmUniversal\Laravel\OrmUniversalServiceProvider` provider (auto-discovery).
- Config published at `config/orm-universal.php` (default mode: `hybrid`).

## Running the runner

```bash
php examples/run.php   # prints that the fragments require a Laravel app; exit 0
```

## Files

| File | Content |
|---|---|
| `basic.php` | `OrmUniversal::table('sellers')` facade next to vanilla Eloquent. |
| `intermediate.php` | `HasUniversalQueries` trait, `universal()/recordset()` macros, light hydration. |
| `advanced.php` | Recordsets, cursor/streaming, profiler, and `LaravelCompatibilityLayer::report()`. |
| `expert.php` | `Ude` kernel, adapters, config, NoSQL registration, and `flush()` for Octane/queues. |
| `extreme.php` | Full adoption cycle: commands, codemod dry-run, parity, and benchmark. |

## Notes

- `recordset()` returns **zero hydration** (arrays); for objects use `light()`/`entity()`.
- UDE does not inject soft deletes: declare `whereNull('deleted_at')` when applicable.
- Command reports are written to `storage/ude/`.
- Codemods (`ude:migrate:codemod`) are dry-run and never write files.
