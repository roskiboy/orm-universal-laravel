---
name: ude-laravel
description: Capa de integracion Laravel de OrmUniversal (orm-universal/laravel) - service provider, modo hibrido con Eloquent, facade OrmUniversal, puente de modelos y comandos ude:*. Use when integrating UDE into a Laravel app, mixing Eloquent with UDE queries, adding HasUniversalQueries, or running ude:* tooling. Triggers: "orm-universal/laravel", "OrmUniversal facade", "HasUniversalQueries", "ude:", "hybrid mode", "Eloquent bridge".
---

# UDE Laravel (orm-universal/laravel)

## Cuándo usar este paquete

- Adoptar UDE dentro de una app Laravel **sin romper Eloquent vanilla** (modo `hybrid`).
- Exponer el motor universal a través del container y del facade `OrmUniversal`.
- Medir, mapear, inspeccionar y migrar persistencia con los comandos `ude:*`.
- Mantener workers largos (Octane, RoadRunner, colas) sin estado contaminado.

El Core (`contracts`, `relational`, `nosql`) no depende de Laravel; este paquete es la capa
de integración (`illuminate/console`, `illuminate/database`, `illuminate/support`).

## APIs clave (nombres reales verificados)

- Provider: `OrmUniversal\Laravel\OrmUniversalServiceProvider` (auto-discovery). Registra
  `Ude::class` como singleton, publica `config/orm-universal.php` con tag
  `orm-universal-config`, registra 16 comandos y los resets de Octane.
- Kernel: `OrmUniversal\Laravel\Ude` → `dialects()`, `types()`, `business()`, `contexts()`,
  `profiler()`, `optimizer()`, `nosql()`, `features()`, `compatibility()`, `connections()`,
  `plugins()`, `bootPlugins()`, `bootNoSql(array $config)`, `flush()`.
- Facade: `OrmUniversal\Laravel\Facades\OrmUniversal` → `table($table, $connection = null)`,
  `executor($connection = null)`, `kernel()`.
- Puente Eloquent:
  - Trait `OrmUniversal\Laravel\Concerns\HasUniversalQueries` →
    `Seller::universal()`, `Seller::universalTable()`, `Seller::universalConnection()`,
    `$seller->universalRecordset()`.
  - Macros globales en `Illuminate\Database\Eloquent\Builder`:
    `Seller::query()->universal()` y `Seller::query()->recordset()`.
- Adapters: `Adapters\LaravelConnectionAdapter` (`executor()`, `dialectFor()`, `pdoHandle()`,
  `summary()`, `flush()`), `LaravelModelAdapter`, `LaravelBuilderAdapter`,
  `LaravelMigrationAdapter`, `LaravelFeatureRegistry`, `LaravelContainerAdapter` (PSR-11).
- Compatibilidad: `Compatibility\LaravelCompatibilityLayer` (`mode()`, `use()`,
  `allowsLegacyApis()`, `guardRawSql()`, `guardLegacyApi()`, `report()`) y enum
  `CompatibilityMode` (`legacy|compatibility|hybrid|optimized|native|strict`).

## Patrones recomendados

### Consulta híbrida (Eloquent + UDE sobre la misma BD)

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// Vanilla Eloquent sigue igual
$names = \App\Models\Seller::query()->orderBy('name')->pluck('name');

// Misma intención por el motor universal
$sellers = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();

foreach ($sellers as $seller) {
    echo $seller['name']; // zero hydration: array
}
```

`OrmUniversal::table()` usa la conexión de `config('database.default')` o la que indiques;
reutiliza el PDO vivo de Laravel y detecta versión real del servidor.

### Puente de modelos (opt-in)

```php
use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

Seller::universal()->where('is_active', true)->get();
Seller::query()->recordset();      // macro, no altera resultados Eloquent
```

### Hot reads con streaming

```php
$report = OrmUniversal::table('sales')
    ->where('created_at', '>=', '2026-01-01')
    ->light()
    ->cursor();

foreach ($report as $row) {
    // stdClass, cursor PDO real
}
```

### Flush en workers largos

El provider ya escucha `RequestTerminated`, `TaskTerminated`, `TickTerminated`,
`JobProcessed` y `JobFailed` y llama `flush()`. Si construyes un kernel propio, haz
`$kernel->flush()` al terminar la unidad de trabajo.

### Configuración

`config/orm-universal.php`: `compatibility_mode`, `performance_mode`, `n_plus_one`,
`profiler`, `cache`, `destructive_statements`, `tooling.report_path` (por defecto
`storage/ude`), `nosql.default` y `nosql.drivers` (`mongodb`, `redis`, `in_memory`).
Tras cambiarla: `php artisan config:clear`.

## Comandos `ude:*` (firmas reales)

| Comando | Uso |
|---|---|
| `ude:install [--force]` | Publica config y ejecuta `ude:doctor`. |
| `ude:doctor [--connection=]` | Diagnóstico de entorno/dialecto/capacidades. |
| `ude:compatibility [--json] [--connection=]` | Reporte de compatibilidad. |
| `ude:dialect:list` | Dialectos registrados. |
| `ude:dialect:inspect [--connection=]` | Capacidades del dialecto activo. |
| `ude:schema:inspect [table] [--connection=] [--json]` | Tablas, columnas, índices, FKs. |
| `ude:schema:diff [--connection=] [--snapshot=] [--json]` | Diff sin ejecutar DDL. |
| `ude:schema:generate {table} [--write] [--force]` | Modelo + migración desde el esquema. |
| `ude:model:inspect {model?} [--json]` | Relaciones, scopes, casts, observers. |
| `ude:map [--connection=] [--json]` | Mapa BD → tablas → modelos → queries. |
| `ude:project:analyze [--json] [--path=]` | Clasificación de patrones de persistencia. |
| `ude:migrate:analyze [--json]` | Clasifica migraciones. |
| `ude:migrate:codemod [--diff] [--backup] [--interactive] [--path=*]` | Sugerencias; **nunca** escribe. |
| `ude:query:inspect {table} [--select=*] [--where=*] [--limit=] [--explain]` | SQL, bindings, plan, fingerprint. |
| `ude:benchmark {table} [--rows=1000] [--connection=]` | Laravel vs UDE (zero/light/cursor). |
| `ude:cache:clear [--connection=]` | Limpia profiler, contextos y adapters. |

Los reportes se escriben como JSON + Markdown en `storage/ude/`.

## Errores comunes

- Asumir que `recordset()` devuelve modelos Eloquent: devuelve **zero hydration** (arrays).
  Usa `light()` para `stdClass`, `entity()` o `models(fn ($row) => ...)`.
- Llamar al facade fuera de una app Laravel inicializada (sin container/`config()`).
- Creer que UDE inyecta soft deletes: no hay magia; declara `whereNull('deleted_at')`.
- Actualizar/borrar sin `where` en modo `strict`/`deny` y recibir excepción (correcto).
- Cambiar `config/orm-universal.php` sin `config:clear`.
- Ejecutar `ude:migrate:codemod` esperando escritura: es dry-run por defecto y jamás escribe.

## Límites honestos

- Es una capa de integración: la lógica de motores vive en `relational`/`nosql`.
- Hace feature detection, no version checks duros de Laravel.
- `ude:schema:generate` no sobrescribe sin `--force`; los codemods no escriben archivos.
- La paridad se verifica con tests (`tests/Feature/Ude/SellerUdeTest.php` en el monorepo),
  no por promesa.
- Los drivers NoSQL solo se registran si la extensión/cliente está disponible.
- Los fragmentos de `examples/` de este paquete **no son ejecutables standalone**: requieren
  una app Laravel con el paquete instalado.
