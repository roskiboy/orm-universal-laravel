# Prompt: migrar rutas calientes de Eloquent a UDE manteniendo paridad total

## Contexto

Migración gradual y no destructiva en una app Laravel. El modo por defecto `hybrid` garantiza
que Eloquent siga funcionando. Guía canónica: `docs/ude/MIGRATION_FROM_LARAVEL.md`.

## Objetivo

Pide a un agente que ejecute este protocolo de 12 pasos:

1. Instalar: `composer require orm-universal/laravel` + `php artisan ude:install`.
2. Analizar: `php artisan ude:project:analyze` (reporte en `storage/ude/`).
3. Backup: rama/commit antes de tocar nada.
4. Confirmar `compatibility_mode=hybrid`.
5. Provider activo por auto-discovery.
6. (Opcional) `HasUniversalQueries` en modelos candidatos.
7. (Opcional) `ude:model:inspect` para ver relaciones/scopes.
8. Sugerencias: `ude:migrate:codemod --diff` (nunca escribe).
9. Migrar reads pesados a `recordset()/cursor()` en servicios/repositorios.
10. Validar migraciones: `ude:migrate:analyze` y `ude:schema:diff --snapshot=`.
11. Benchmark: `ude:benchmark <tabla> --rows=10000`.
12. Evaluar `compatibility_mode=optimized` solo con paridad verificada.

## Reglas anti-rotura

- No cambiar escrituras, eventos, casts ni soft deletes en la misma fase.
- Un test de paridad por ruta migrada: mismo resultado, mismo orden, mismos nulos.
- Si el resultado difiere, revertir a Eloquent: híbrido lo permite.
- Los codemods son dry-run: el agente jamás debe escribir archivos "silenciosamente".
- Documentar cada decisión y el rollback.

## Entregables

- Read paths migrados con tests de paridad.
- `storage/ude/benchmark.json|.md` antes/después.
- Lista de pendientes con causa (relación Eloquent, evento, cast, soft delete).

## Verificación

```bash
php artisan ude:project:analyze
php artisan ude:benchmark sellers --rows=1000
php artisan test
```
