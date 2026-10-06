# Ejemplos de integración: orm-universal/laravel

> **Estos archivos NO se ejecutan standalone.** Son fragmentos de integración para copiar
> dentro de una app Laravel con `orm-universal/laravel` instalado (provider con
> auto-discovery). `run.php` solo imprime este requisito y termina con exit 0.

## Requisitos para usarlos de verdad

```bash
composer require orm-universal/contracts orm-universal/relational orm-universal/nosql orm-universal/laravel
php artisan ude:install
```

- Laravel 12.x / 13.x.
- Provider `OrmUniversal\Laravel\OrmUniversalServiceProvider` (auto-discovery).
- Config publicada en `config/orm-universal.php` (modo por defecto: `hybrid`).

## Ejecución del runner

```bash
php examples/run.php   # imprime que los fragmentos requieren una app Laravel; exit 0
```

## Archivos

| Archivo | Contenido |
|---|---|
| `basic.php` | Facade `OrmUniversal::table('sellers')` junto a Eloquent vanilla. |
| `intermediate.php` | Trait `HasUniversalQueries`, macros `universal()/recordset()`, hidratación light. |
| `advanced.php` | Recordsets, cursor/streaming, profiler y `LaravelCompatibilityLayer::report()`. |
| `expert.php` | Kernel `Ude`, adapters, config, registro NoSQL y `flush()` para Octane/queues. |
| `extreme.php` | Ciclo de adopción completo: comandos, codemod dry-run, paridad y benchmark. |

## Notas

- `recordset()` devuelve **zero hydration** (arrays); para objetos usa `light()`/`entity()`.
- UDE no inyecta soft deletes: declara `whereNull('deleted_at')` cuando aplique.
- Los reportes de los comandos se escriben en `storage/ude/`.
- Los codemods (`ude:migrate:codemod`) son dry-run y nunca escriben archivos.
