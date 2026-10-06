<?php

// FRAGMENTO de integración: requiere una app Laravel con orm-universal/laravel
// instalado. NO ejecutar con `php basic.php` (no hay container/config).

declare(strict_types=1);

use App\Models\Seller;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// --- Eloquent vanilla: intacto, sin cambios ---------------------------------

$eloquent = Seller::query()
    ->where('is_active', true)
    ->orderBy('name')
    ->pluck('name');

// --- La misma intención por el motor universal ------------------------------

$ude = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->pluck('name');

// Resultados equivalentes en modo hybrid (paridad verificada por tests).
assert(array_values($ude) === $eloquent->all());

// Recordset zero hydration: arrays, no modelos.
$recordset = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();

foreach ($recordset as $seller) {
    // $seller es array asociativo
    $name = $seller['name'];
}

// Sin magia de soft deletes: si el modelo usa SoftDeletes, se declara.
$notTrashed = OrmUniversal::table('sellers')
    ->whereNull('deleted_at')
    ->count();

// Conexión explícita cuando la app tiene varias.
$fromReports = OrmUniversal::table('sellers', 'reports')
    ->limit(50)
    ->get();
