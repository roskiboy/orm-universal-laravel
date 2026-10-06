# Security Policy — orm-universal/laravel

## Versiones soportadas

| Versión | Soportada | Notas |
|---|---|---|
| `0.1.x` | Sí | Línea activa |
| `< 0.1` | No | No publicada |

Matriz de compatibilidad: PHP 8.3–8.5 y Laravel 12.x/13.x (`illuminate/*`). Fuera de esa
matriz no se garantizan parches de seguridad.

## Canal de reporte privado

No abras issues públicos para vulnerabilidades. Usa el reporte privado de GitHub:

- https://github.com/roskiboy/orm-universal-laravel/security/advisories/new
- Alternativa: contacta a los mantenedores de In-Tech a través del repositorio.

Incluye versión del paquete, versión de Laravel, descripción, PoC mínima, impacto y
mitigación. Acuse de recibo orientativo: 72 horas laborables.

## Alcance

En alcance:

- Ruptura de las garantías del modo híbrido (por ejemplo, que los macros alteren
  consultas Eloquent o inyecten filtros).
- Ejecución no solicitada de SQL/DDL desde comandos `ude:*` (deben ser dry-run salvo
  `--write`/`--force`).
- Exposición de credenciales de conexión o datos de consulta en reportes, profiler o
  mensajes de error originada en este paquete.
- Evasión de `LaravelCompatibilityLayer` en modo `strict`.
- Ejecución destructiva que ignore `destructive_statements`.

Fuera de alcance:

- Vulnerabilidades de Laravel, `illuminate/*`, PDO, MongoDB o Redis.
- SQL crudo provisto por la aplicación a `SqlFragment::trusted()`.
- Configuraciones de `.env` del proyecto consumidor.

## Mitigaciones entregadas

- Los adaptadores no modifican modelos ni el query builder de Eloquent.
- `LaravelBuilderAdapter` omite cláusulas desconocidas en lugar de adivinarlas.
- `LaravelMigrationAdapter` presenta reporte de seguridad antes de ejecutar DDL.
- `ude:schema:generate` requiere `--write`; `ude:migrate:codemod` es dry-run.
- El flush de contexto/profiler evita fugas de estado entre requests.

## Buenas prácticas para consumidores

- Usa `strict` en proyectos nuevos que no dependan de API legadas.
- Define `destructive_statements` según el entorno (`deny` en producción si aplica).
- No incluyas los reportes de `storage/ude/` en artefactos públicos.
