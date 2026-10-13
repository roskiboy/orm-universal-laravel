---
name: ude-laravel
description: Laravel integration layer of OrmUniversal (orm-universal/laravel) - service provider, hybrid mode with Eloquent, OrmUniversal facade, model bridge, and ude:* commands. Use when integrating UDE into a Laravel app, mixing Eloquent with UDE queries, adding HasUniversalQueries, or running ude:* tooling. Triggers: "orm-universal/laravel", "OrmUniversal facade", "HasUniversalQueries", "ude:", "hybrid mode", "Eloquent bridge".
---

# UDE Laravel (orm-universal/laravel)

## When to use this package

- Adopt UDE inside a Laravel app **without breaking vanilla Eloquent** (`hybrid` mode).
- Expose the universal engine through the container and the `OrmUniversal` facade.
- Measure, map, inspect, and migrate persistence with the `ude:*` commands.
- Keep long-lived workers (Octane, RoadRunner, queues) free of contaminated state.

The Core (`contracts`, `relational`, `nosql`) does not depend on Laravel; this package is
the integration layer (`illuminate/console`, `illuminate/database`, `illuminate/support`).

## Key APIs (real verified names)

- Provider: `OrmUniversal\Laravel\OrmUniversalServiceProvider` (auto-discovery). Registers
  `Ude::class` as a singleton, publishes `config/orm-universal.php` with tag
  `orm-universal-config`, registers 16 commands and the Octane resets.
- Kernel: `OrmUniversal\Laravel\Ude` → `dialects()`, `types()`, `business()`, `contexts()`,
  `profiler()`, `optimizer()`, `nosql()`, `features()`, `compatibility()`, `connections()`,
  `plugins()`, `bootPlugins()`, `bootNoSql(array $config)`, `flush()`.
- Facade: `OrmUniversal\Laravel\Facades\OrmUniversal` → `table($table, $connection = null)`,
  `executor($connection = null)`, `kernel()`.
- Eloquent bridge:
  - `OrmUniversal\Laravel\Concerns\HasUniversalQueries` trait →
    `Seller::universal()`, `Seller::universalTable()`, `Seller::universalConnection()`,
    `$seller->universalRecordset()`.
  - Global macros on `Illuminate\Database\Eloquent\Builder`:
    `Seller::query()->universal()` and `Seller::query()->recordset()`.
- Adapters: `Adapters\LaravelConnectionAdapter` (`executor()`, `dialectFor()`, `pdoHandle()`,
  `summary()`, `flush()`), `LaravelModelAdapter`, `LaravelBuilderAdapter`,
  `LaravelMigrationAdapter`, `LaravelFeatureRegistry`, `LaravelContainerAdapter` (PSR-11).
- Compatibility: `Compatibility\LaravelCompatibilityLayer` (`mode()`, `use()`,
  `allowsLegacyApis()`, `guardRawSql()`, `guardLegacyApi()`, `report()`) and enum
  `CompatibilityMode` (`legacy|compatibility|hybrid|optimized|native|strict`).

## Recommended patterns

### Hybrid query (Eloquent + UDE over the same DB)

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// Vanilla Eloquent stays the same
$names = \App\Models\Seller::query()->orderBy('name')->pluck('name');

// Same intent through the universal engine
$sellers = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();

foreach ($sellers as $seller) {
    echo $seller['name']; // zero hydration: array
}
```

`OrmUniversal::table()` uses the `config('database.default')` connection or the one you
specify; it reuses the live Laravel PDO and detects the real server version.

### Model bridge (opt-in)

```php
use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

Seller::universal()->where('is_active', true)->get();
Seller::query()->recordset();      // macro, does not alter Eloquent results
```

### Hot reads with streaming

```php
$report = OrmUniversal::table('sales')
    ->where('created_at', '>=', '2026-01-01')
    ->light()
    ->cursor();

foreach ($report as $row) {
    // stdClass, real PDO cursor
}
```

### Flush in long workers

The provider already listens to `RequestTerminated`, `TaskTerminated`, `TickTerminated`,
`JobProcessed`, and `JobFailed` and calls `flush()`. If you build your own kernel, call
`$kernel->flush()` when the unit of work finishes.

### Configuration

`config/orm-universal.php`: `compatibility_mode`, `performance_mode`, `n_plus_one`,
`profiler`, `cache`, `destructive_statements`, `tooling.report_path` (default
`storage/ude`), `nosql.default`, and `nosql.drivers` (`mongodb`, `redis`, `in_memory`).
After changing it: `php artisan config:clear`.

## `ude:*` commands (real signatures)

| Command | Usage |
|---|---|
| `ude:install [--force]` | Publishes config and runs `ude:doctor`. |
| `ude:doctor [--connection=]` | Environment/dialect/capability diagnostics. |
| `ude:compatibility [--json] [--connection=]` | Compatibility report. |
| `ude:dialect:list` | Registered dialects. |
| `ude:dialect:inspect [--connection=]` | Capabilities of the active dialect. |
| `ude:schema:inspect [table] [--connection=] [--json]` | Tables, columns, indexes, FKs. |
| `ude:schema:diff [--connection=] [--snapshot=] [--json]` | Diff without running DDL. |
| `ude:schema:generate {table} [--write] [--force]` | Model + migration from the schema. |
| `ude:model:inspect {model?} [--json]` | Relations, scopes, casts, observers. |
| `ude:map [--connection=] [--json]` | Map DB → tables → models → queries. |
| `ude:project:analyze [--json] [--path=]` | Persistence pattern classification. |
| `ude:migrate:analyze [--json]` | Classifies migrations. |
| `ude:migrate:codemod [--diff] [--backup] [--interactive] [--path=*]` | Suggestions; **never** writes. |
| `ude:query:inspect {table} [--select=*] [--where=*] [--limit=] [--explain]` | SQL, bindings, plan, fingerprint. |
| `ude:benchmark {table} [--rows=1000] [--connection=]` | Laravel vs UDE (zero/light/cursor). |
| `ude:cache:clear [--connection=]` | Clears profiler, contexts, and adapters. |

Reports are written as JSON + Markdown in `storage/ude/`.

## Common errors

- Assuming `recordset()` returns Eloquent models: it returns **zero hydration** (arrays).
  Use `light()` for `stdClass`, `entity()`, or `models(fn ($row) => ...)`.
- Calling the facade outside an initialized Laravel app (without container/`config()`).
- Believing UDE injects soft deletes: there is no magic; declare `whereNull('deleted_at')`.
- Updating/deleting without `where` in `strict`/`deny` mode and getting an exception (correct).
- Changing `config/orm-universal.php` without `config:clear`.
- Running `ude:migrate:codemod` expecting writes: it is dry-run by default and never writes.

## Honest limits

- It is an integration layer: engine logic lives in `relational`/`nosql`.
- It does feature detection, not hard Laravel version checks.
- `ude:schema:generate` does not overwrite without `--force`; codemods do not write files.
- Parity is verified with tests (`tests/Feature/Ude/SellerUdeTest.php` in the monorepo),
  not by promise.
- NoSQL drivers are only registered if the extension/client is available.
- The fragments in this package's `examples/` **are not standalone executable**: they
  require a Laravel app with the package installed.
