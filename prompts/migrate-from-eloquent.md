# Prompt: migrate hot Eloquent paths to UDE while keeping full parity

## Context

Gradual, non-destructive migration in a Laravel app. The default `hybrid` mode guarantees
that Eloquent keeps working. Canonical guide: `docs/ude/MIGRATION_FROM_LARAVEL.md`.

## Goal

Ask an agent to run this 12-step protocol:

1. Install: `composer require orm-universal/laravel` + `php artisan ude:install`.
2. Analyze: `php artisan ude:project:analyze` (report in `storage/ude/`).
3. Backup: branch/commit before touching anything.
4. Confirm `compatibility_mode=hybrid`.
5. Provider active via auto-discovery.
6. (Optional) `HasUniversalQueries` on candidate models.
7. (Optional) `ude:model:inspect` to see relations/scopes.
8. Suggestions: `ude:migrate:codemod --diff` (never writes).
9. Migrate heavy reads to `recordset()/cursor()` in services/repositories.
10. Validate migrations: `ude:migrate:analyze` and `ude:schema:diff --snapshot=`.
11. Benchmark: `ude:benchmark <table> --rows=10000`.
12. Evaluate `compatibility_mode=optimized` only with verified parity.

## Anti-breakage rules

- Do not change writes, events, casts, or soft deletes in the same phase.
- One parity test per migrated path: same result, same order, same nulls.
- If the result differs, revert to Eloquent: hybrid allows it.
- Codemods are dry-run: the agent must never write files "silently".
- Document every decision and the rollback.

## Deliverables

- Read paths migrated with parity tests.
- `storage/ude/benchmark.json|.md` before/after.
- List of pending items with cause (Eloquent relation, event, cast, soft delete).

## Verification

```bash
php artisan ude:project:analyze
php artisan ude:benchmark sellers --rows=1000
php artisan test
```
