# Security Policy — orm-universal/laravel

## Supported versions

| Version | Supported | Notes |
|---|---|---|
| `0.1.x` | Yes | Active line |
| `< 0.1` | No | Not published |

Compatibility matrix: PHP 8.3–8.5 and Laravel 12.x/13.x (`illuminate/*`). Outside that
matrix, security patches are not guaranteed.

## Private reporting channel

Do not open public issues for vulnerabilities. Use GitHub private reporting:

- https://github.com/roskiboy/orm-universal-laravel/security/advisories/new
- Alternative: contact the In-Tech maintainers through the repository.

Include the package version, Laravel version, description, minimal PoC, impact, and
mitigation. Indicative acknowledgment: 72 business hours.

## Scope

In scope:

- Breaking the hybrid-mode guarantees (for example, macros altering Eloquent queries or
  injecting filters).
- Unrequested SQL/DDL execution from `ude:*` commands (they must be dry-run unless
  `--write`/`--force`).
- Exposure of connection credentials or query data in reports, profiler, or error
  messages originating in this package.
- Bypassing `LaravelCompatibilityLayer` in `strict` mode.
- Destructive execution that ignores `destructive_statements`.

Out of scope:

- Vulnerabilities in Laravel, `illuminate/*`, PDO, MongoDB, or Redis.
- Raw SQL provided by the application to `SqlFragment::trusted()`.
- `.env` configurations of the consuming project.

## Delivered mitigations

- Adapters do not modify Eloquent models or the query builder.
- `LaravelBuilderAdapter` omits unknown clauses instead of guessing them.
- `LaravelMigrationAdapter` presents a safety report before running DDL.
- `ude:schema:generate` requires `--write`; `ude:migrate:codemod` is dry-run.
- Context/profiler flush prevents state leaks between requests.

## Best practices for consumers

- Use `strict` in new projects that do not depend on legacy APIs.
- Define `destructive_statements` per environment (`deny` in production if applicable).
- Do not include `storage/ude/` reports in public artifacts.
