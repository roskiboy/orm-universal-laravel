# Laravel — orm-universal/laravel

Real integration architecture: provider, `Ude` kernel, facade, and configuration.

## Service provider

`OrmUniversal\Laravel\OrmUniversalServiceProvider` (auto-discovery declared in
`composer.json` → `extra.laravel.providers`).

`register()`:

- `mergeConfigFrom(config/orm-universal.php, 'orm-universal')`.
- `Ude::class` singleton created with `Ude::create()`; applies the config's
  `compatibility_mode` and runs `bootNoSql()`.
- Container alias `'orm-universal'`.

`boot()`:

- In console: publishes the `orm-universal-config` tag and registers the 16 `ude:*` commands.
- `registerEloquentBridge()`: `universal()` and `recordset()` macros on
  `Illuminate\Database\Eloquent\Builder` (if the class exists).
- `registerOctaneResets()`: calls `Ude::flush()` on `RequestTerminated`,
  `TaskTerminated`, `TickTerminated`, `JobProcessed`, and `JobFailed` when they exist.

`provides()` returns `[Ude::class, 'orm-universal']`.

## `Ude` kernel

`OrmUniversal\Laravel\Ude` exposes the platform state:

| Method | Returns |
|---|---|
| `create(): self` | Kernel with default dialects and types |
| `dialects(): DialectRegistry` | Available dialects |
| `types(): TypeRegistry` | Universal types |
| `business(): BusinessRegistry` | Scopes/specs/operations |
| `contexts(): ContextScope` | Octane-safe contexts |
| `profiler(): InMemoryProfiler` | Query events |
| `optimizer(): Optimizer` | Optimization pipeline |
| `nosql(): DocumentStore` | NoSQL collections |
| `features(): LaravelFeatureRegistry` | Laravel feature detection |
| `compatibility(): LaravelCompatibilityLayer` | Adoption mode |
| `connections(): LaravelConnectionAdapter` | Bridge to Laravel connections |
| `plugins(): PluginRegistry` | Registered plugins |
| `bootPlugins(): array` | Registers, boots, and returns names |
| `bootNoSql(array $config): void` | Registers drivers from the config |
| `flush(): void` | Resets contexts and profiler |

## `OrmUniversal` facade

`OrmUniversal\Laravel\Facades\OrmUniversal` (accessor `Ude::class`).

Own methods:

- `table(string $table, ?string $connection = null): QueryBuilder`
- `executor(?string $connection = null): QueryExecutor`
- `kernel(): Ude`

Methods delegated via docblock: `dialects()`, `types()`, `business()`, `nosql()`,
`profiler()`, `features()`, `compatibility()`, `connections()`.

## Configuration (`config/orm-universal.php`)

| Key | Values | Default |
|---|---|---|
| `compatibility_mode` | legacy, compatibility, hybrid, optimized, native, strict | `hybrid` |
| `performance_mode` | safe, balanced, fast, extreme | `balanced` |
| `n_plus_one.mode` | ignore, warning, log, exception, telemetry | `warning` |
| `n_plus_one.threshold` | integer | `2` |
| `profiler.enabled` / `limit` | bool / integer | `true` / `500` |
| `cache.enabled`, `store`, `ttl` | opt-in | `false`, null, `60` |
| `destructive_statements` | allow, warn, require_confirmation, deny | `warn` |
| `tooling.report_path` | path | `storage/ude` |
| `tooling.model_paths` | paths | `app/Models` |
| `tooling.model_namespace` | namespace | `App\Models` |
| `nosql.default` | `mongodb`, `redis`, `in-memory` | `mongodb` |
| `nosql.drivers.*` | enabled and parameters | see file |

Equivalent environment variables with the `UDE_` prefix (`UDE_COMPATIBILITY_MODE`,
`UDE_MONGODB_URI`, `UDE_REDIS_HOST`, …).

## Feature detection

`LaravelFeatureRegistry` never compares version numbers: it detects by reflection
(`octane`, `eloquent_builder_macros`, `model_macroable`, `query_builder_morph`,
`schema_blueprint_foreign_id`, `connection_pdo_accessor`, `pagination_cursor`,
`db_transaction_retry`, `queue_serializes_models`).
