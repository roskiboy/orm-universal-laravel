<?php

// FRAGMENTO de integración: requiere una app Laravel con orm-universal/laravel
// instalado. NO ejecutar standalone.

declare(strict_types=1);

use App\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;
use OrmUniversal\Relational\Ast\Column;

// --- Puente opt-in: trait en el modelo --------------------------------------

final class SellerWithUniversal extends Model
{
    use HasUniversalQueries;

    protected $table = 'sellers';
}

// QueryBuilder UDE sobre la tabla/conexión del modelo.
$active = SellerWithUniversal::universal()
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->get();

// Recordset completo de la tabla.
$all = (new SellerWithUniversal)->universalRecordset();

// Helpers del trait.
$table = SellerWithUniversal::universalTable();       // 'sellers'
$connection = SellerWithUniversal::universalConnection();

// --- Macros globales en Eloquent Builder ------------------------------------

$recordset = Seller::query()->recordset();            // no altera resultados Eloquent
$universal = Seller::query()->universal()->where('is_active', true)->get();

// --- Hidratación ------------------------------------------------------------

$light = SellerWithUniversal::universal()->light()->first();   // stdClass

// Modelos Eloquent reales a partir de filas crudas (factory).
$models = SellerWithUniversal::universal()
    ->models(fn (array $row) => Seller::query()->newFromBuilder($row))
    ->limit(100)
    ->get();
