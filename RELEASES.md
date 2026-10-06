# Release Process — orm-universal/laravel

Checklist operativo para publicar una versión del paquete.

## 1. Preparación

- [ ] `main` verde en CI (PHP 8.3, 8.4, 8.5).
- [ ] `composer install` limpio.
- [ ] `composer validate` sin errores.
- [ ] `vendor/bin/phpunit` en verde.
- [ ] `vendor/bin/phpstan analyse` sin errores (nivel 5).
- [ ] `vendor/bin/pint` sin cambios pendientes.
- [ ] `git status` limpio.

## 2. Compatibilidad Laravel

- [ ] Probar contra Laravel 12.x y 13.x (rangos `illuminate/console|database|support`).
- [ ] Revisar `LaravelFeatureRegistry`: cualquier feature nueva detectada con
  `class_exists`/reflexión, sin comparar versiones.
- [ ] Confirmar que el provider funciona sin Octane instalado.

## 3. Contratos con el ecosistema

- [ ] Dependencias `orm-universal/contracts|relational|nosql` en `^0.1`.
- [ ] Si cambia `DocumentDriverInterface` o `DocumentStore`, coordinar con `nosql`.
- [ ] Si cambia la API de relaciones, coordinar con `relational`.

## 4. Changelog y documentación

- [ ] `CHANGELOG.md` con `Unreleased` revisada.
- [ ] Mover a `## [X.Y.Z] - YYYY-MM-DD`.
- [ ] `BREAKING CHANGE` con guía de migración si aplica.
- [ ] `docs/` refleja el modo híbrido, comandos y adaptadores reales.
- [ ] Snapshot de documentación del tag (`DOCUMENTATION.md`).

## 5. Tag y publicación

- [ ] `composer.json` en `X.Y.Z`.
- [ ] Commit `chore(release): vX.Y.Z`.
- [ ] Tag anotado `vX.Y.Z`.
- [ ] Push de commit y tag.
- [ ] Release en GitHub con notas.
- [ ] Verificar resolución en Composer.

## Rollback

No se reescriben tags. Se publica un `PATCH` correctivo y se anota la retirada de la
versión defectuosa en las release notes.
