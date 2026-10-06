# Seguridad — orm-universal/laravel

## Principios

1. **Eloquent no se modifica.** No hay interceptores globales que puedan cambiar
   resultados, filtros ni transacciones.
2. **Modo `strict` opcional** para equipos que quieren errores ante API legadas o SQL
   crudo sin clasificar:
   ```php
   $layer->guardRawSql($sql, $explicit); // exige SqlFragment::trusted()/parameterized()
   $layer->guardLegacyApi('query');      // lanza QueryException
   ```
3. **Valores como bindings** en todo el motor relacional subyacente; las cláusulas
   copiadas por `LaravelBuilderAdapter` conservan los valores como datos, no como SQL.
4. **Política de sentencias destructivas** configurable:
   `allow`, `warn`, `require_confirmation`, `deny` (`destructive_statements`). Se aplica
   en la ejecución de migraciones vía `LaravelMigrationAdapter`, que además presenta
   reporte de seguridad previo.
5. **Comandos conservadores**: `ude:schema:generate` requiere `--write`; `ude:migrate:codemod`
   es dry-run implícito y nunca escribe silenciosamente.

## Credenciales y datos

- La conexión se toma de Laravel (`config('database.connections.*')`); el paquete no
  almacena credenciales propias.
- Las credenciales NoSQL viven en `config/orm-universal.php` / `.env`
  (`UDE_MONGODB_URI`, `UDE_REDIS_*`). No las incluyas en reportes ni en logs.
- `ude:doctor` y `ude:map` pueden incluir nombres de conexión/tabla; revisa la salida
  antes de compartirla.
- El profiler (`profiler.enabled`, `limit`) registra eventos de consulta; en entornos
  sensibles, desactívalo o limita su retención.

## Estado en workers

El provider reinicia contexto y profiler en `RequestTerminated`, `TaskTerminated`,
`TickTerminated`, `JobProcessed` y `JobFailed`. Si ejecutas la plataforma fuera de esos
eventos (procesos propios), llama `Ude::flush()` explícitamente.

## Superficie de ataque relevante

- Entrada de usuario usada en `where(Column::..., $value)`: correcto (binding).
- Entrada de usuario en `SqlFragment::trusted()`: responsabilidad de la aplicación.
- Patrones `regex` en NoSQL: no se sanean.
- `ude:project:analyze` inspecciona el proyecto, no ejecuta SQL de usuario.

## Alcance

Los fallos de Laravel, PDO, MongoDB o Redis quedan fuera del alcance de este paquete.
Reporta vulnerabilidades de la capa de integración por el canal privado de `SECURITY.md`.
