# `ude:*` Commands — OrmUniversal\Laravel

The 23 commands registered by `OrmUniversalServiceProvider`. All of them extend
`BaseCommand` and resolve the executor with the given connection.

| Command | Signature | What it does |
|---|---|---|
| `ude:install` | `{--force}` | Publishes `config/orm-universal.php` (tag `orm-universal-config`) and runs `ude:doctor` |
| `ude:doctor` | `{--connection=}` | Diagnoses PHP, Laravel, package version, connection, driver, PDO/mongodb/redis extensions, mode, server version, dialect, capability count, ping, and dialect limitations |
| `ude:compatibility` | `{--json} {--connection=}` | Compatibility report: PHP, Laravel, package, database, and driver |
| `ude:dialect:list` | — | Lists registered dialects and their default version line |
| `ude:dialect:inspect` | `{--connection=}` | Shows the active dialect, server version, and negotiated capabilities |
| `ude:schema:inspect` | `{table?} {--connection=} {--json}` | Inspects real tables, columns, indexes, and FKs |
| `ude:schema:diff` | `{--connection=} {--snapshot=} {--json}` | Compares the current schema against a desired snapshot |
| `ude:schema:generate` | `{table} {--connection=} {--write} {--force}` | Generates a model and migration skeleton from the real schema; never overwrites without `--force` |
| `ude:model:inspect` | `{model?} {--json}` | Analyzes models: relations, scopes, casts, accessors/mutators, and observers |
| `ude:map` | `{--connection=} {--json}` | Project map: database → tables → models → relations → queries |
| `ude:project:analyze` | `{--json} {--path=}` | Analyzes models, queries, `DB::` calls, raw SQL, migrations, and services |
| `ude:migrate:analyze` | `{--json}` | Classifies migrations: compatible, convertible, requires review, unsupported, engine-specific |
| `ude:migrate:codemod` | `{--diff} {--backup} {--interactive} {--path=*}` | Conservative codemod: suggests conversions without modifying files (implicit dry-run) |
| `ude:auto:enable` | `{--connection=*} {--path=} {--dry-run} {--diff} {--backup} {--interactive} {--write} {--force}` | Converts the `driver` entries of `config/database.php` to `ude-*` with php-parser (surgical replacement, optional backup, git-dirty guard) |
| `ude:index:advise` | `{table?} {--connection=} {--json} {--from-profiler}` | Recommends indexes from the real schema or from the monitor samples (`--from-profiler`); never creates indexes |
| `ude:index:generate` | `{table?} {--connection=} {--from-profiler} {--path=} {--write} {--force} {--json}` | Generates a migration with the recommended indexes; dry-run by default, never touches data |
| `ude:types:map` | `{model?} {--connection=} {--dry-run} {--diff} {--backup} {--write} {--force} {--json}` | Compares real columns with `$casts` and suggests/fixes them (decimals always string); writes only with `--write` and models with `$casts` |
| `ude:query:inspect` | `{table} {--connection=} {--select=*} {--where=*} {--limit=10} {--explain}` | Compiles and inspects a query: SQL, bindings, plan, fingerprint, and capabilities |
| `ude:benchmark` | `{table} {--rows=1000} {--connection=}` | Reproducible benchmark: Laravel Query Builder vs UDE (zero/light/Eloquent) |
| `ude:cache:clear` | `{--connection=}` | Clears platform caches: metadata, connections, profiler, and context |

## Examples

```bash
php artisan ude:doctor
php artisan ude:schema:inspect sellers --json
php artisan ude:query:inspect orders --where=status=paid --limit=5 --explain
php artisan ude:migrate:codemod --diff --path=database/migrations
php artisan ude:benchmark sellers --rows=5000
php artisan ude:cache:clear
```

## Security notes

- `ude:schema:generate` does not write without `--write` and does not overwrite without `--force`.
- `ude:migrate:codemod` is dry-run by default: it never modifies files silently.
- `ude:auto:enable` is dry-run by default; it only writes with `--write` and refuses if
  `config/database.php` has uncommitted changes (unless `--force`). Recommended:
  prior branch/commit + `--backup`.
- The destructive statement policy (`destructive_statements`) is respected during
  migration execution through `LaravelMigrationAdapter`.

## Reports

Analysis commands write to `tooling.report_path` (`storage/ude/` by default).
`--json` allows consuming the output from CI or scripts.
