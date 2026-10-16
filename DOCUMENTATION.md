# Documentation Policy — ORM Universal Laravel

## Principle

Documentation describes the **real API and behavior**. Classes, methods, or capabilities
that do not exist in `src/` are not documented, and behavior not verified by tests is not
promised. In the Laravel layer it also matters to document guarantees: what the
integration does not do (does not touch Eloquent, does not guess clauses, does not write
without `--write`).

## Structure

```text
docs/
├── contracts/    (contracts.md, installation.md, quickstart.md, security.md,
│                  testing.md, troubleshooting.md, releases.md, CHANGELOG.md)
├── relational/   (architecture, ast, expressions, dialects, drivers, pdo, recordset,
│                  schema, migrations, optimizer, relations, business-logic, types,
│                  performance, security, migration/, CHANGELOG.md, ...)
├── nosql/        (nosql.md, filters.md, aggregation.md, drivers.md, security.md,
│                  testing.md, troubleshooting.md, CHANGELOG.md, ...)
└── laravel/      (laravel.md, eloquent.md, relations.md, commands.md, hybrid-mode.md,
                   examples.md, security.md, CHANGELOG.md, ...)
```

Governance files live at the package root (`SECURITY.md`, `CONTRIBUTING.md`,
`ROADMAP.md`, `RELEASES.md`, `AGENTS.md`).

## Versioning by tag (snapshot)

- Each `vX.Y.Z` tag freezes `docs/` and the root `CHANGELOG.md` of that version.
- `main` may describe unpublished work, labeled `unreleased`.
- When tagging, verify that the command table and the compatibility modes match the code.
- Security notes may be added retroactively to affected tags without rewriting them.

## Rules

1. A change to a public API, provider, `Ude`, facade, adapters, macros, or commands
   requires updating the corresponding `docs/<layer>/` files in the same PR.
2. Examples must be runnable against the documented version.
3. Document limitations (MorphTo not inferred, omitted clauses, dry-run) next to the
   capability, not in footnotes.
4. Technical English; identifiers and commands are not translated.
5. Files of 40 to 120 lines.

## Review

- Every `src/` PR states whether it affects docs.
- Docs-only PRs pass the style gates; they do not require new tests.
- Claims about Laravel compatibility must be covered by CI or by a reproducible
  verification described in the PR.
