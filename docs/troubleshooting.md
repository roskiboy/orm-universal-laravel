# Troubleshooting — orm-universal/laravel

## El provider no se registra

1. Confirma `composer dump-autoload`.
2. Revisa `extra.laravel.providers` en el `composer.json` del paquete.
3. Si tu Laravel no usa auto-discovery, regístralo a mano en `config/app.php`.
4. `php artisan ude:doctor` falla con "Command not found": comprueba que el provider
   está cargado y que ejecutas en consola.

## `config/orm-universal.php` no existe

Es normal: el provider hace `mergeConfigFrom`. Para tener el archivo editable:

```bash
php artisan vendor:publish --tag=orm-universal-config
```

## La fachada `OrmUniversal` no resuelve

- Verifica el alias `OrmUniversal` → `OrmUniversal\Laravel\Facades\OrmUniversal`.
- El accessor es `Ude::class`; confirma con `app()->bound(Ude::class)`.
- Tras cambiar configuración de providers, limpia caches: `php artisan optimize:clear`.

## `ude:doctor` no puede conectar

Revisa la conexión Laravel indicada (`--connection=`), que PDO tenga el driver
correspondiente (`php -m`) y que la base de datos exista. El comando imprime la tabla de
checks aunque falle la conexión y devuelve `FAILURE`.

## Los macros `universal()`/`recordset()` no existen

El provider los registra solo si `Illuminate\Database\Eloquent\Builder` existe. Si usas
una versión sin esa clase o un stub propio, los macros no se instalan; usa
`OrmUniversal::table()` o el trait `HasUniversalQueries`.

## `strict` lanza `QueryException` con SQL crudo

Es el comportamiento documentado: envuelve el SQL en `SqlFragment::trusted()` o
`SqlFragment::parameterized()` para declarar su naturaleza, o cambia de modo.

## Comportamiento raro tras varias requests (Octane/queues)

Llama `Ude::flush()` al final de procesos propios. En Octane el provider ya escucha los
eventos terminados; si desactivas esos listeners, gestiona el flush manualmente.

## `UnsupportedFeatureException` de NoSQL

El driver no soporta la capacidad (por ejemplo, agregación en Redis). Revisa
`OrmUniversal::nosql()->driver()->capabilities()` y las alternativas de la excepción.

## `ude:schema:generate` no escribe

Comportamiento intencional: añade `--write`; para sobrescribir, además `--force`.
`ude:migrate:codemod` siempre es dry-run.

## El benchmark o los análisis tardan demasiado

Ajusta `tooling.model_paths` y usa `--limit`/`--rows` menores. Los reportes quedan en
`storage/ude/`; limpia con `php artisan ude:cache:clear`.

## Errores de PHPStan por clases de Octane

Están ignorados en `phpstan.neon.dist` (`class.notFound`) de forma deliberada: las
integraciones opcionales se resuelven con `class_exists`.
