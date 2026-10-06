# Comandos `ude:*` — orm-universal/laravel

Los 16 comandos registrados por `OrmUniversalServiceProvider`. Todos extienden
`BaseCommand` y resuelven el executor con la conexión indicada.

| Comando | Firma | Qué hace |
|---|---|---|
| `ude:install` | `{--force}` | Publica `config/orm-universal.php` (tag `orm-universal-config`) y ejecuta `ude:doctor` |
| `ude:doctor` | `{--connection=}` | Diagnostica PHP, Laravel, versión del paquete, conexión, driver, extensiones PDO/mongodb/redis, modo, versión del servidor, dialecto, número de capacidades, ping y limitaciones del dialecto |
| `ude:compatibility` | `{--json} {--connection=}` | Reporte de compatibilidad: PHP, Laravel, paquete, base de datos y driver |
| `ude:dialect:list` | — | Lista dialectos registrados y su línea de versión por defecto |
| `ude:dialect:inspect` | `{--connection=}` | Muestra dialecto activo, versión del servidor y capacidades negociadas |
| `ude:schema:inspect` | `{table?} {--connection=} {--json}` | Inspecciona tablas, columnas, índices y FKs reales |
| `ude:schema:diff` | `{--connection=} {--snapshot=} {--json}` | Compara el esquema actual contra un snapshot deseado |
| `ude:schema:generate` | `{table} {--connection=} {--write} {--force}` | Genera esqueleto de modelo y migración desde el esquema real; nunca sobrescribe sin `--force` |
| `ude:model:inspect` | `{model?} {--json}` | Analiza modelos: relaciones, scopes, casts, accessors/mutators y observers |
| `ude:map` | `{--connection=} {--json}` | Mapa del proyecto: base de datos → tablas → modelos → relaciones → queries |
| `ude:project:analyze` | `{--json} {--path=}` | Analiza modelos, queries, llamadas `DB::`, SQL crudo, migraciones y servicios |
| `ude:migrate:analyze` | `{--json}` | Clasifica migraciones: compatible, convertible, requiere revisión, no soportado, específico del motor |
| `ude:migrate:codemod` | `{--diff} {--backup} {--interactive} {--path=*}` | Codemod conservador: sugiere conversiones sin modificar archivos (dry-run implícito) |
| `ude:query:inspect` | `{table} {--connection=} {--select=*} {--where=*} {--limit=10} {--explain}` | Compila e inspecciona una consulta: SQL, bindings, plan, fingerprint y capacidades |
| `ude:benchmark` | `{table} {--rows=1000} {--connection=}` | Benchmark reproducible: Laravel Query Builder vs UDE (zero/light/Eloquent) |
| `ude:cache:clear` | `{--connection=}` | Limpia caches de la plataforma: metadatos, conexiones, profiler y contexto |

## Ejemplos

```bash
php artisan ude:doctor
php artisan ude:schema:inspect sellers --json
php artisan ude:query:inspect orders --where=status=paid --limit=5 --explain
php artisan ude:migrate:codemod --diff --path=database/migrations
php artisan ude:benchmark sellers --rows=5000
php artisan ude:cache:clear
```

## Notas de seguridad

- `ude:schema:generate` no escribe sin `--write` y no sobrescribe sin `--force`.
- `ude:migrate:codemod` es dry-run por defecto: nunca modifica archivos silenciosamente.
- La política de sentencias destructivas (`destructive_statements`) se respeta en la
  ejecución de migraciones a través de `LaravelMigrationAdapter`.

## Reportes

Los comandos de análisis escriben en `tooling.report_path` (`storage/ude/` por defecto).
`--json` permite consumir la salida desde CI o scripts.
