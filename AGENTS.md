# AGENTS.md — orm-universal/laravel

Guía para agentes de IA y contribuyentes automatizados.

## Arquitectura mínima

```text
src/
├── OrmUniversalServiceProvider.php   (auto-discovery, config, comandos, macros, Octane)
├── Ude.php                           (kernel: registries, contextos, profiler, NoSQL)
├── Adapters/                         (conexión, feature registry, modelo, builder, migración)
├── Compatibility/                    (seis modos de adopción)
├── Concerns/HasUniversalQueries.php  (trait opcional para modelos)
├── Console/                          (16 comandos ude:*)
├── Analysis/                         (ModelInspector, ProjectAnalyzer, Codemod)
├── Facades/OrmUniversal.php
└── config/orm-universal.php
```

Depende de `contracts`, `relational`, `nosql` e `illuminate/console|database|support`.
El Core de esos paquetes nunca importa `Illuminate\*`; la dependencia solo existe en
esta capa.

## Regla principal: no romper Eloquent vanilla

1. **Prohibido modificar el comportamiento de Eloquent.** Los macros `universal()` y
   `recordset()` son aditivos; jamás inyectan filtros ni alteran resultados, orden,
   casts, eventos o transacciones.
2. **No hay interceptores globales.** Si el usuario no invoca la plataforma, el
   comportamiento debe ser Laravel puro.
3. **No adivinar cláusulas.** `LaravelBuilderAdapter` copia solo lo que entiende; lo
   desconocido se omite.
4. **No tocar modelos.** `LaravelModelAdapter` lee metadatos por reflexión sin ejecutar el
   constructor del modelo ni modificarlo.
5. **Detección por features, nunca por versión.** Usa `LaravelFeatureRegistry`
   (`class_exists`, reflexión). Prohibido comparar números de versión de Laravel.
6. **Estado por request.** Cualquier estado acumulado debe limpiarse en `Ude::flush()`,
   invocado desde los eventos de Octane/queue.

## Reglas del repo

- `declare(strict_types=1)` en todos los archivos.
- Los comandos son conservadores: dry-run por defecto; `--write` y `--force` explícitos.
- Las garantías de `LaravelCompatibilityLayer::report()` no se relajan sin release
  mayor y guía de migración.
- No añadas dependencias fuera de `illuminate/*` y los paquetes `orm-universal/*`.

## Comandos

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint
```

Gates: los cuatro sin errores. CI prueba PHP 8.3–8.5; los tests de la capa Laravel se
ejecutan contra el framework instalado.

## Convenciones

- PSR-4: `OrmUniversal\Laravel\` → `src/`; tests `OrmUniversal\Laravel\Tests\`.
- Comandos: firma `ude:<área>:<acción>` y descripción en español como en el resto de la
  consola del paquete.
- Nombres de clases y métodos en inglés; docs y textos de consola en español.
- `final class` por defecto; adaptadores sin estado salvo cachés explícitas.

## Al modificar adaptadores

1. Añade/actualiza test en `tests/AdaptersTest.php` o `tests/CompatibilityTest.php`.
2. Verifica que Eloquent sigue intacto (resultado, orden, nulls).
3. Actualiza `docs/eloquent.md`, `docs/relations.md` o `docs/hybrid-mode.md` según
   corresponda.

## No tocar

- No edites `README.md`, `CHANGELOG.md` de release ni `LICENSE` sin instrucción.
- No modifiques `.github/` ni `composer.json` salvo tarea de release.
