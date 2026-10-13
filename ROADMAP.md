# Roadmap — orm-universal/laravel

Documento vivo. Intención, no compromiso de fechas. Línea soportada: `0.4.x`.

## Current (0.4.x)

- Intercepto `Fulltext` (MySQL/MariaDB/PostgreSQL) y shapes de optimize-10/11 (fechas,
  JSON, `Exists`/`whereHas`, row values, `NullSafeEquals`); macros y `dynamicWhere`
  verificados sobre drivers `ude-*`.
- **`ude:parity:api`** + `Analysis\ParityAnalyzer` (optimize-9): auditoría de paridad
  Laravel 13 con clasificación paridad/alias/na/diferido/gap y reporte en `storage/ude`.
  Resultado vigente: 0 gaps en `Query\Builder`/`Eloquent\Builder`/`Schema\Blueprint`.
- Intercepto AST con joins multi-condición en el `ON` (paridad fila a fila).
- Service provider con auto-discovery, config fusionada/publicable y alias de fachada.
- Kernel `Ude` con registries, contextos, profiler, optimizer, plugins y DocumentStore.
- `LaravelConnectionAdapter` reutilizando el PDO vivo y la versión real del servidor.
- `LaravelFeatureRegistry` por reflexión (sin chequeos de versión).
- `LaravelCompatibilityLayer` con seis modos y guardas en `strict`.
- Puente Eloquent aditivo: trait `HasUniversalQueries`, macros `universal()`/`recordset()`,
  adaptadores de modelo, builder y migración.
- 16 comandos `ude:*`.
- Reinicios Octane/queue en eventos terminados.
- Modo híbrido verificado en el monorepo (`tests/Feature/Ude/SellerUdeTest.php`).

## Next

- Ampliar la cobertura de tests de comandos con salida JSON estable.
- Documentar recetas de adopción de `hybrid` a `optimized` con métricas de benchmark.
- Endurecer la detección y el reporte de cláusulas no copiadas por
  `LaravelBuilderAdapter`.
- Profundizar en `ude:migrate:codemod`: más patrones y mejor diff.

## Future

- Modo `native` con reporte de features solo-Laravel no cubiertas.
- Integración de los contratos de relaciones con el motor de relaciones de `relational`
  de forma opt-in.
- Cache de consultas opt-in conectada al cache de Laravel (`cache.store`).

## Experimental

- `ude:doctor --json` para consumo en CI.
- Codemod asistido con `--interactive` más granular por archivo.
- Plugin registry expuesto a aplicaciones para contribuir adaptadores.

## Research

- Estrategia de migración progresiva Eloquent → UDE sin downtime.
- Semántica de transacciones a través del pool de conexiones de Laravel y Octane.
- Compatibilidad con futuras versiones de Laravel 13+ manteniendo detección por features.
