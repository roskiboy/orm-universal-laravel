# Contributing — orm-universal/laravel

Thanks for contributing to the integration layer. The central rule: **do not break
vanilla Eloquent**.

## Requirements

- PHP `^8.3`
- Composer 2.x
- Laravel 12.x/13.x for integration tests

## Branch flow

- `main`: always green and publishable.
- `feat/<description>`, `fix/<description>`, `docs/<description>`, `chore/<description>`.
- One PR per purpose; rebase onto `main` before opening it.

## Conventional Commits

```text
feat(commands): add --json output to ude:map
fix(provider): guard macro registration when Builder is missing
perf(connections): cache executors per Laravel connection
docs(hybrid): document Octane flush guarantees
```

Breaking change:

```text
feat(adapters)!: drop legacy clause copying without explicit opt-in

BREAKING CHANGE: LaravelBuilderAdapter no longer copies clauses outside the documented
list. Review docs/eloquent.md before upgrading.
```

## Mandatory gates

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/pint
```

All four must pass. CI tests PHP 8.3–8.5 and clones the monorepo dependencies as path
repositories.

## Package rules

- `declare(strict_types=1)` in every file.
- Do not modify Eloquent behavior: the macros are additive.
- Do not guess clauses: `LaravelBuilderAdapter` omits the unknown.
- Feature detection by reflection, never by version number.
- Commands are conservative: dry-run by default, explicit `--write`/`--force`.
- Per-request state is reset in `Ude::flush()`; do not add mutable singletons.

## Pull requests

1. Problem and solution described.
2. Tests covering the adapter/command touched.
3. `CHANGELOG.md` updated under `Unreleased`.
4. `docs/` updated if the API, a command, or a hybrid-mode guarantee changes.
5. Confirm that `vendor/bin/phpunit` and phpstan pass on PHP 8.3 at minimum.

All contributions are accepted under the project's MIT license.
