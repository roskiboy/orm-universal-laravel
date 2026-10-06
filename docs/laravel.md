# Laravel — orm-universal/laravel

Arquitectura de integración real: provider, kernel `Ude`, fachada y configuración.

## Service provider

`OrmUniversal\Laravel\OrmUniversalServiceProvider` (auto-discovery declarado en
`composer.json` → `extra.laravel.providers`).

`register()`:

- `mergeConfigFrom(config/orm-universal.php, 'orm-universal')`.
- Singleton `Ude::class` creado con `Ude::create()`; aplica
  `compatibility_mode` del config y ejecuta `bootNoSql()`.
- Alias de contenedor `'orm-universal'`.

`boot()`:

- En consola: publica el tag `orm-universal-config` y registra los 16 comandos `ude:*`.
- `registerEloquentBridge()`: macros `universal()` y `recordset()` en
  `Illuminate\Database\Eloquent\Builder` (si la clase existe).
- `registerOctaneResets()`: llama `Ude::flush()` en `RequestTerminated`,
  `TaskTerminated`, `TickTerminated`, `JobProcessed` y `JobFailed` cuando existen.

`provides()` devuelve `[Ude::class, 'orm-universal']`.

## Kernel `Ude`

`OrmUniversal\Laravel\Ude` expone el estado de la plataforma:

| Método | Devuelve |
|---|---|
| `create(): self` | Kernel con dialectos y tipos por defecto |
| `dialects(): DialectRegistry` | Dialectos disponibles |
| `types(): TypeRegistry` | Tipos universales |
| `business(): BusinessRegistry` | Scopes/specs/operaciones |
| `contexts(): ContextScope` | Contextos Octane-safe |
| `profiler(): InMemoryProfiler` | Eventos de consulta |
| `optimizer(): Optimizer` | Pipeline de optimización |
| `nosql(): DocumentStore` | Colecciones NoSQL |
| `features(): LaravelFeatureRegistry` | Detección de features de Laravel |
| `compatibility(): LaravelCompatibilityLayer` | Modo de adopción |
| `connections(): LaravelConnectionAdapter` | Puente a conexiones Laravel |
| `plugins(): PluginRegistry` | Plugins registrados |
| `bootPlugins(): array` | Registra, arranca y devuelve nombres |
| `bootNoSql(array $config): void` | Registra drivers del config |
| `flush(): void` | Reinicia contextos y profiler |

## Fachada `OrmUniversal`

`OrmUniversal\Laravel\Facades\OrmUniversal` (accessor `Ude::class`).

Métodos propios:

- `table(string $table, ?string $connection = null): QueryBuilder`
- `executor(?string $connection = null): QueryExecutor`
- `kernel(): Ude`

Métodos delegados vía docblock: `dialects()`, `types()`, `business()`, `nosql()`,
`profiler()`, `features()`, `compatibility()`, `connections()`.

## Configuración (`config/orm-universal.php`)

| Clave | Valores | Default |
|---|---|---|
| `compatibility_mode` | legacy, compatibility, hybrid, optimized, native, strict | `hybrid` |
| `performance_mode` | safe, balanced, fast, extreme | `balanced` |
| `n_plus_one.mode` | ignore, warning, log, exception, telemetry | `warning` |
| `n_plus_one.threshold` | entero | `2` |
| `profiler.enabled` / `limit` | bool / entero | `true` / `500` |
| `cache.enabled`, `store`, `ttl` | opt-in | `false`, null, `60` |
| `destructive_statements` | allow, warn, require_confirmation, deny | `warn` |
| `tooling.report_path` | ruta | `storage/ude` |
| `tooling.model_paths` | rutas | `app/Models` |
| `tooling.model_namespace` | namespace | `App\Models` |
| `nosql.default` | `mongodb`, `redis`, `in-memory` | `mongodb` |
| `nosql.drivers.*` | enabled y parámetros | ver archivo |

Variables de entorno equivalentes con prefijo `UDE_` (`UDE_COMPATIBILITY_MODE`,
`UDE_MONGODB_URI`, `UDE_REDIS_HOST`, …).

## Detección de features

`LaravelFeatureRegistry` nunca compara números de versión: detecta por reflexión
(`octane`, `eloquent_builder_macros`, `model_macroable`, `query_builder_morph`,
`schema_blueprint_foreign_id`, `connection_pdo_accessor`, `pagination_cursor`,
`db_transaction_retry`, `queue_serializes_models`).
