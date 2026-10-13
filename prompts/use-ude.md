# Prompt: integrate orm-universal/laravel into a Laravel app

## Context

The app uses Eloquent and wants to adopt the universal engine in hybrid mode, without
changing existing behavior. The `orm-universal/laravel` package provides a provider with
auto-discovery, a facade, a model bridge, adapters, and `ude:*` commands.

## Goal

Ask an agent to:

1. Install the packages and publish the configuration:

```bash
composer require orm-universal/contracts orm-universal/relational orm-universal/nosql orm-universal/laravel
php artisan ude:install
```

2. Verify environment and compatibility:

```bash
php artisan ude:doctor
php artisan ude:compatibility --json
php artisan ude:dialect:list
```

3. Keep `compatibility_mode=hybrid` in `config/orm-universal.php`.
4. Use the facade for new reads:

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

$active = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();
```

5. Optionally add `HasUniversalQueries` to a model and expose `Seller::universal()`.
6. Write an Eloquent ↔ UDE parity test (same result and same order).

## Rules

- Do not break vanilla Eloquent: the package promises equivalent results, but the test
  proves it in **your** app.
- `recordset()` is zero hydration (arrays). For objects: `light()`, `entity()`,
  `models(fn ($row) => ...)`.
- Soft deletes: declare `->whereNull('deleted_at')` explicitly.
- `UPDATE`/`DELETE` without `where`: configure it with `destructive_statements` and confirm.
- In long workers, trust the provider's `flush()` or call it when finishing.
- After touching config: `php artisan config:clear`.

## Deliverables

- Packages installed + config published and versioned.
- A parity test and one migrated hot read case (or a documented decision).
- `ude:doctor`/`ude:compatibility` report in `storage/ude/`.

## Verification

```bash
php artisan ude:doctor
php artisan test
vendor/bin/phpstan analyse -c phpstan-packages.neon
```

## Repo examples

`examples/basic.php` (facade), `intermediate.php` (trait/macros), `advanced.php`
(recordsets and streaming), `expert.php` (kernel/adapters/config), `extreme.php`
(commands and adoption cycle). Remember: they are integration fragments, not standalone
scripts.
