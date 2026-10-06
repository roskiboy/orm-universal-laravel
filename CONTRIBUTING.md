# Contributing — orm-universal/laravel

Gracias por contribuir a la capa de integración. La regla central: **no romper Eloquent
vanilla**.

## Requisitos

- PHP `^8.3`
- Composer 2.x
- Laravel 12.x/13.x para tests de integración

## Flujo de ramas

- `main`: siempre verde y publicable.
- `feat/<descripcion>`, `fix/<descripcion>`, `docs/<descripcion>`, `chore/<descripcion>`.
- Un PR por propósito; rebase sobre `main` antes de abrir.

## Conventional Commits

```text
feat(commands): add --json output to ude:map
fix(provider): guard macro registration when Builder is missing
perf(connections): cache executors per Laravel connection
docs(hybrid): document Octane flush guarantees
```

Cambio incompatible:

```text
feat(adapters)!: drop legacy clause copying without explicit opt-in

BREAKING CHANGE: LaravelBuilderAdapter ya no copia cláusulas fuera de la lista
documentada. Revisa docs/eloquent.md antes de actualizar.
```

## Gates obligatorios

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint
```

Los cuatro deben pasar. El CI prueba PHP 8.3–8.5 y clona las dependencias del monorepo
como path repositories.

## Reglas del paquete

- `declare(strict_types=1)` en todos los archivos.
- No modifiques comportamiento de Eloquent: los macros son aditivos.
- No adivines cláusulas: `LaravelBuilderAdapter` omite lo desconocido.
- Detección de features por reflexión, nunca por número de versión.
- Los comandos son conservadores: dry-run por defecto, `--write`/`--force` explícitos.
- El estado por request se reinicia en `Ude::flush()`; no añadas singletons mutables.

## Pull requests

1. Problema y solución descritos.
2. Tests que cubran el adaptador/comando tocado.
3. `CHANGELOG.md` actualizado bajo `Unreleased`.
4. `docs/` actualizado si cambia la API, un comando o una garantía del modo híbrido.
5. Confirma que `vendor/bin/phpunit` y phpstan pasan en PHP 8.3 como mínimo.

Toda contribución se acepta bajo la licencia MIT del proyecto.
