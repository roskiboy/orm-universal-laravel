# Prompt: integrar orm-universal/laravel en una app Laravel

## Contexto

La app usa Eloquent y quiere adoptar el motor universal en modo híbrido, sin cambiar
comportamiento existente. El paquete `orm-universal/laravel` aporta provider con
auto-discovery, facade, puente de modelos, adapters y comandos `ude:*`.

## Objetivo

Pide a un agente que:

1. Instale los paquetes y publique la configuración:

```bash
composer require orm-universal/contracts orm-universal/relational orm-universal/nosql orm-universal/laravel
php artisan ude:install
```

2. Verifique entorno y compatibilidad:

```bash
php artisan ude:doctor
php artisan ude:compatibility --json
php artisan ude:dialect:list
```

3. Mantenga `compatibility_mode=hybrid` en `config/orm-universal.php`.
4. Use el facade para lecturas nuevas:

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

$active = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();
```

5. Opcionalmente añada `HasUniversalQueries` a un modelo y exponga `Seller::universal()`.
6. Escriba un test de paridad Eloquent ↔ UDE (mismo resultado y mismo orden).

## Reglas

- No romper Eloquent vanilla: el paquete promete resultados equivalentes, pero el test lo
  demuestra en **tu** app.
- `recordset()` es zero hydration (arrays). Para objetos: `light()`, `entity()`,
  `models(fn ($row) => ...)`.
- Soft deletes: declara `->whereNull('deleted_at')` explícitamente.
- `UPDATE`/`DELETE` sin `where`: configúralo con `destructive_statements` y confirma.
- En workers largos, confía en el `flush()` del provider o llámalo al terminar.
- Tras tocar config: `php artisan config:clear`.

## Entregables

- Paquetes instalados + config publicada y versionada.
- Un test de paridad y un caso de lectura caliente migrado (o decisión documentada).
- Reporte de `ude:doctor`/`ude:compatibility` en `storage/ude/`.

## Verificación

```bash
php artisan ude:doctor
php artisan test
vendor/bin/phpstan analyse -c phpstan-packages.neon
```

## Ejemplos del repo

`examples/basic.php` (facade), `intermediate.php` (trait/macros), `advanced.php` (recordsets
y streaming), `expert.php` (kernel/adapters/config), `extreme.php` (comandos y ciclo de
adopción). Recuerda: son fragmentos de integración, no scripts standalone.
