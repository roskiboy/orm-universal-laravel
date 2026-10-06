# Releases — orm-universal/laravel

## Política de versiones

- SemVer `MAJOR.MINOR.PATCH`. Versión actual: `0.1.0`.
- En `0.x`, un cambio incompatible incrementa `MINOR` y exige `BREAKING CHANGE`.
- La API pública es `OrmUniversalServiceProvider`, `Ude`, la fachada, los adaptadores, el
  trait `HasUniversalQueries`, los macros `universal()`/`recordset()` y los comandos
  `ude:*` (firma y propósito).
- Cambiar garantías del modo híbrido es un cambio de contrato: documéntalo con la misma
  severidad que un cambio de firma.

## Artefactos

| Artefacto | Descripción |
|---|---|
| Tag Git | `vX.Y.Z` en `roskiboy/orm-universal-laravel` |
| `CHANGELOG.md` | Keep a Changelog |
| Docs | Snapshot por tag (`DOCUMENTATION.md`) |
| Composer | `orm-universal/laravel` |

## Checklist de release

1. `composer validate` sin errores.
2. `vendor/bin/phpunit` en verde.
3. `vendor/bin/phpstan analyse` sin errores (nivel 5).
4. `vendor/bin/pint` sin cambios pendientes.
5. Verificar compatibilidad con Laravel 12.x y 13.x (rango de `illuminate/*`).
6. Revisar que los 16 comandos `ude:*` siguen registrados y con firma coherente.
7. Actualizar `CHANGELOG.md` (mover `Unreleased` a `X.Y.Z` con fecha).
8. Confirmar dependencias `contracts`, `relational` y `nosql` en `^0.1`.
9. Crear tag anotado `vX.Y.Z` y release notes.
10. Congelar snapshot de `docs/` para el tag.

## Notas de release

Incluye: modos de compatibilidad, cambios en adaptadores y macros, cambios en comandos,
garantías de Eloquent y guía de migración si hay `BREAKING CHANGE`.

## Rollback

No se reescriben tags: se publica un `PATCH` correctivo y se anota la retirada de la
versión defectuosa.
