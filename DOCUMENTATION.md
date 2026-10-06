# Política de Documentación — orm-universal/laravel

## Principio

La documentación describe **API y comportamiento reales**. En esta capa importa además
documentar garantías: qué no hace la integración (no tocar Eloquent, no adivinar
cláusulas, no escribir sin `--write`).

## Estructura

```text
docs/
├── installation.md
├── quickstart.md
├── laravel.md        (provider, kernel Ude, fachada, config)
├── eloquent.md       (macros, trait, adaptador de builder)
├── relations.md      (LaravelModelAdapter, RelationDefinition)
├── commands.md       (16 comandos ude:*)
├── hybrid-mode.md    (modos y garantías)
├── examples.md
├── security.md
├── testing.md
├── troubleshooting.md
└── releases.md
```

## Versionado por tag (snapshot)

- Cada tag `vX.Y.Z` congela `docs/` y el `CHANGELOG.md` de esa versión.
- `main` puede describir trabajo no publicado, etiquetado `unreleased`.
- Al etiquetar, verifica que la tabla de comandos y los modos de compatibilidad coinciden
  con el código.
- Las notas de seguridad pueden añadirse retroactivamente a tags afectados sin
  reescribirlos.

## Reglas

1. Un cambio en el provider, `Ude`, la fachada, los adaptadores, los macros o un comando
   exige actualizar `docs/` en el mismo PR.
2. Los ejemplos deben poder ejecutarse contra la versión documentada.
3. Documenta las limitaciones (MorphTo no inferido, cláusulas omitidas, dry-run) junto a
   la capacidad, no en notas al pie.
4. Español técnico; identificadores y comandos sin traducir.
5. Archivos de 40 a 120 líneas.

## Revisión

- Todo PR de `src/` indica si afecta a docs.
- Los PRs de solo documentación pasan los gates de estilo; no requieren tests nuevos.
- Las afirmaciones sobre compatibilidad con Laravel deben estar cubiertas por CI o por
  una verificación reproducible descrita en el PR.
