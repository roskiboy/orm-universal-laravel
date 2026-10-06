# Quickstart — orm-universal/laravel

## 1. Instalar y diagnosticar

```bash
composer require orm-universal/laravel
php artisan ude:install
php artisan ude:doctor
```

`ude:install` deja el modo híbrido activo: Eloquent sigue funcionando sin cambios.

## 2. Primera consulta con la fachada

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

$rows = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->get();
```

`OrmUniversal::table()` usa la conexión por defecto (`config('database.default')`) salvo
que pases otra. El executor reutiliza el PDO vivo de Laravel y detecta la versión real
del servidor para resolver el dialecto.

## 3. Recordset sobre Eloquent

```php
// Vanilla Eloquent intacto
$names = App\Models\Seller::query()->orderBy('name')->pluck('name');

// Mismo modelo, motor universal
$recordset = App\Models\Seller::universal()
    ->where('is_active', true)
    ->recordset();

foreach ($recordset as $row) {
    // ...
}
```

## 4. Macros globales

```php
Seller::query()->recordset(); // RecordsetInterface de la tabla del modelo
```

`universal()` y `recordset()` son macros globales de `Illuminate\Database\Eloquent\Builder`
registradas por el provider; no alteran los resultados de Eloquent.

## 5. Configuración esencial

```dotenv
UDE_COMPATIBILITY_MODE=hybrid
UDE_PERFORMANCE_MODE=balanced
UDE_N_PLUS_ONE_MODE=warning
UDE_PROFILER_ENABLED=true
UDE_DESTRUCTIVE_STATEMENTS=warn
```

Todas las claves y valores por defecto están en `config/orm-universal.php`.

## 6. Siguiente paso

```bash
php artisan ude:project:analyze
php artisan ude:map
```

Ambos generan reportes en `storage/ude/` (ruta configurable en `tooling.report_path`).
