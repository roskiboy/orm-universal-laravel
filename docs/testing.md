# Testing — orm-universal/laravel

## Comandos

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

CI: PHP 8.3, 8.4 y 8.5. El workflow clona `contracts`, `relational` y `nosql` como path
repositories y requiere `DEPS_TOKEN` solo mientras esos repos sean privados.

## Configuración

`phpunit.xml.dist`:

- Suite `Laravel` sobre `tests/`, bootstrap `vendor/autoload.php`.
- `failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`.

`phpstan.neon.dist`: nivel 5 sobre `src/`, con `class.notFound` ignorado porque Octane y
eventos de cola se resuelven con `class_exists`.

## Suite existente

| Archivo | Cobertura |
|---|---|
| `tests/AdaptersTest.php` | Adaptadores (modelo, builder, migración, conexión) |
| `tests/CompatibilityTest.php` | Modos de compatibilidad, guardas y garantías |

## Integración en el monorepo

El README documenta el test de paridad híbrida
`tests/Feature/Ude/SellerUdeTest.php` en la aplicación `tms`: verifica paridad
Eloquent↔UDE, recordsets, escrituras y soft deletes explícitos. Esa prueba vive en el
monorepo, no en el paquete.

## Al escribir tests

- Usa modelos de test reales de Eloquent; no simules `Builder` con mocks frágiles.
- Cubre:
  - `LaravelModelAdapter::metadata()` y `relationDefinitions()` para cada relación
    mapeada y una no mapeada (`MorphTo`) que debe omitirse.
  - `LaravelBuilderAdapter` con cláusulas conocidas y con una desconocida que se omite.
  - `LaravelCompatibilityLayer` en `strict` (raw SQL y API legada).
  - `LaravelFeatureRegistry` (detección booleana, sin versiones).
  - Los comandos `ude:*` con `artisan()` en modo test, sin tocar datos reales.
- No dependas de extensiones opcionales: los adaptadores deben funcionar con SQLite.
- Limpia el estado del contenedor entre tests (`Ude::flush()`).

## Criterio de aceptación

`vendor/bin/phpunit` y `vendor/bin/phpstan analyse` en verde, `composer validate` sin
errores y `vendor/bin/pint` sin cambios pendientes.
