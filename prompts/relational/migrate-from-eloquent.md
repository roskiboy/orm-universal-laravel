# Prompt: migrate Eloquent hot paths to UDE relational without breaking anything

## Context

The app works with Eloquent. The goal is to speed up specific reads (reports, large
listings, exports) with the universal engine, keeping Eloquent as the source of truth
for writes and model semantics.

## Goal

Ask an agent to migrate **only hot reads** following this protocol:

1. Rest: `git checkout -b ude-migration` or a previous commit. Never migrate without a safety net.
2. Measurement: `php artisan ude:query:inspect <tabla> --limit=10` and
   `php artisan ude:benchmark <tabla> --rows=1000` to record the baseline.
3. Parity first: write a test comparing Eloquent vs UDE for the exact query
   (same order, same nulls, same casts). Real model in the repo:
   `tests/Feature/Ude/SellerUdeTest.php`.
4. Minimal change: replace only the read path with

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;

$rows = OrmUniversal::table('sellers')
    ->where('is_active', true)
    ->orderBy('name')
    ->recordset(); // or ->light()->cursor() for large datasets
```

5. Explicit semantics: UDE does **not** apply `deleted_at IS NULL`; if the model uses
   `SoftDeletes`, add `->whereNull('deleted_at')`.
6. Writes: leave them in Eloquent. If a write must go through UDE, use a transaction and
   verify visibility from Eloquent in the test.
7. Final measurement and comparison; if there is no improvement or there is divergence, revert to Eloquent.

## Rules

- Do not change models massively or remove `HasUniversalQueries` if it already exists.
- Do not introduce `SELECT *` if the report only needs specific columns.
- Do not use `update()`/`delete()` without `where` and a matching `DestructiveStatementPolicy`.
- The tests must pass in the full app, not only in the package.

## Deliverables

- New parity test.
- Migrated read path (or a documented decision not to migrate).
- `storage/ude/benchmark.json|.md` files before/after.

## Verification

```bash
php artisan ude:project:analyze
php artisan ude:benchmark sellers --rows=1000
php artisan test
```
