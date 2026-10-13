# Quickstart — orm-universal/laravel

## 1. Install and diagnose

```bash
composer require orm-universal/laravel
php artisan ude:install
php artisan ude:doctor
```

`ude:install` leaves hybrid mode active: Eloquent keeps working unchanged.

## 2. First query with the facade

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

$rows = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->get();
```

`OrmUniversal::table()` uses the default connection (`config('database.default')`) unless
you pass another one. The executor reuses the live Laravel PDO and detects the real
server version to resolve the dialect.

## 3. Recordset over Eloquent

```php
// Vanilla Eloquent intact
$names = App\Models\Seller::query()->orderBy('name')->pluck('name');

// Same model, universal engine
$recordset = App\Models\Seller::universal()
    ->where('is_active', true)
    ->recordset();

foreach ($recordset as $row) {
    // ...
}
```

## 4. Global macros

```php
Seller::query()->recordset(); // RecordsetInterface of the model's table
```

`universal()` and `recordset()` are global macros of `Illuminate\Database\Eloquent\Builder`
registered by the provider; they do not alter Eloquent results.

## 5. Essential configuration

```dotenv
UDE_COMPATIBILITY_MODE=hybrid
UDE_PERFORMANCE_MODE=balanced
UDE_N_PLUS_ONE_MODE=warning
UDE_PROFILER_ENABLED=true
UDE_DESTRUCTIVE_STATEMENTS=warn
```

All keys and default values are in `config/orm-universal.php`.

## 6. Next step

```bash
php artisan ude:project:analyze
php artisan ude:map
```

Both generate reports in `storage/ude/` (path configurable in `tooling.report_path`).
