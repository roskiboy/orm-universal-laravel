# Documentation Policy — orm-universal/laravel

## Principle

Documentation describes the **real API and behavior**. In this layer it also matters to
document guarantees: what the integration does not do (does not touch Eloquent, does not
guess clauses, does not write without `--write`).

## Structure

```text
docs/
├── installation.md
├── quickstart.md
├── laravel.md        (provider, kernel Ude, facade, config)
├── eloquent.md       (macros, trait, builder adapter)
├── relations.md      (LaravelModelAdapter, RelationDefinition)
├── commands.md       (16 ude:* commands)
├── hybrid-mode.md    (modes and guarantees)
├── examples.md
├── security.md
├── testing.md
├── troubleshooting.md
└── releases.md
```

## Versioning by tag (snapshot)

- Each `vX.Y.Z` tag freezes `docs/` and the `CHANGELOG.md` of that version.
- `main` may describe unpublished work, labeled `unreleased`.
- When tagging, verify that the command table and the compatibility modes match the code.
- Security notes may be added retroactively to affected tags without rewriting them.

## Rules

1. A change to the provider, `Ude`, the facade, the adapters, the macros, or a command
   requires updating `docs/` in the same PR.
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
