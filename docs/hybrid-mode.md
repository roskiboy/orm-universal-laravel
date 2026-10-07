# Modo híbrido — orm-universal/laravel

El modo híbrido (default) permite usar Eloquent vanilla y el motor universal en la misma
aplicación, sin que ninguno altere al otro.

## Los seis modos

`OrmUniversal\Contracts\Enums\CompatibilityMode`:

| Modo | Valor | Significado |
|---|---|---|
| Legacy | `legacy` | Solo comportamiento Laravel; la plataforma disponible pasivamente |
| Compatibility | `compatibility` | Adapters activos; resultados byte-compatibles con Laravel |
| Hybrid | `hybrid` | Eloquent vanilla + queries/recordsets UDE en paralelo |
| Optimized | `optimized` | Optimizaciones de plataforma con garantías de compatibilidad |
| Native | `native` | Plataforma primero; features solo-Laravel reportadas |
| Strict | `strict` | API legadas y magia ambigua lanzan excepción |

`allowsLegacyApis()` es `true` en todos salvo `strict`.

## Configuración

```dotenv
UDE_COMPATIBILITY_MODE=hybrid
```

```php
$kernel = app(\OrmUniversal\Laravel\Ude::class);
$kernel->compatibility()->mode(); // CompatibilityMode::Hybrid
```

`LaravelCompatibilityLayer` permite cambiar el modo en runtime (`use()`).

## Garantías declaradas

`report()` devuelve los invariantes del modo activo:

- `eloquent_results_unchanged`
- `ordering_unchanged`
- `null_semantics_unchanged`
- `transaction_semantics_unchanged`
- `casting_unchanged`
- `events_unchanged`

El modo híbrido no intercepta consultas de Eloquent: son garantías de no intervención.

## Guardas de `strict`

```php
$layer->guardRawSql($sql, $explicit); // exige SqlFragment::trusted()/parameterized()
$layer->guardLegacyApi('query');      // rechaza API legada
```

En `strict`, el SQL crudo sin clasificación explícita lanza `QueryException`.

## Conexiones

`LaravelConnectionAdapter` reutiliza el PDO vivo de Laravel:

- `executor($connection, $profiler)` devuelve un `QueryExecutor` cacheado por conexión.
- `dialectFor()` resuelve el dialecto con la versión real del servidor (SQLite consulta
  `select sqlite_version()`).
- `summary()` expone `name`, `driver`, `database` y `server_version`.
- `flush()` reinicia executors y conexiones cacheadas.

## Octane y workers

El provider registra `Ude::flush()` en `RequestTerminated`, `TaskTerminated`,
`TickTerminated`, `JobProcessed` y `JobFailed`. `flush()` reinicia:

- `ContextScope` (contextos inmutables de la petición).
- `InMemoryProfiler`.

No hay estado global mutable fuera del kernel; en workers de larga duración el flush es
la frontera de aislamiento.

## Fast path AST (intercepto opt-in)

Los drivers `ude-*` (`ude-sqlite`, `ude-mysql`, `ude-mariadb`, `ude-pgsql`, `ude-sqlsrv`)
resuelven a la conexión nativa de Laravel con un query builder propio que añade un fast
path AST, activado con:

```dotenv
UDE_AUTO=intercept
# o explícito:
UDE_AUTO_INTERCEPT=true
# kill switch absoluto:
UDE_AUTO_INTERCEPT=false
# config/orm-universal.php
'auto' => ['mode' => 'intercept', 'intercept' => null],
```

Reglas de seguridad del fast path:

- Solo se optimizan formas **whitelisteadas** (from tabla simple, columns string/`*`,
  wheres Basic/In/Null/NotNull/Between/Column/Nested, joins de una comparación de
  columnas, orders, groupBy/having Basic, limit/offset, agregados simples). Cualquier
  otra forma delega en `parent::` sin alterar resultados.
- El SQL se compila con el compilador UDE pero se ejecuta sobre la **misma conexión
  Laravel** (mismo PDO, transacción, `useWritePdo` y fetch mode): se preservan
  `QueryExecuted`, `DB::listen`, el monitor automático y los eventos de modelo.
- Eloquent hidrata igual (filas `stdClass`), incluidos casts, accessors y soft deletes
  explícitos.
- Las conexiones con driver nativo nunca se interceptan, y `UDE_AUTO_INTERCEPT=false`
  desactiva el fast path incluso con `mode=intercept`.
- `ude-oracle` queda passthrough: Laravel no incluye conexión Oracle; requiere un
  resolver OCI8 en la aplicación.

Verificado fila a fila contra vanilla en SQLite y MariaDB (incluye decimales como
string, nulls, joins, agregados y paginación por keyset). Integración SQL Server
implementada a nivel de compilador/conexión, pendiente de infraestructura.

## Adopción progresiva sugerida

1. `legacy`/`compatibility`: medir con `ude:project:analyze` y `ude:map`.
2. `hybrid`: introducir `universal()`/`recordset()` en lecturas concretas.
3. `optimized`: activar cache opt-in y analizar portabilidad/desempeño.
4. `native`/`strict`: solo cuando no queden dependencias de comportamientos legacy.
