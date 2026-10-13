<?php

// Integration FRAGMENT: requires a Laravel app with orm-universal/laravel
// installed. Do NOT run standalone.

declare(strict_types=1);

use App\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;
use OrmUniversal\Relational\Ast\Column;

// --- Opt-in bridge: trait on the model --------------------------------------

final class SellerWithUniversal extends Model
{
    use HasUniversalQueries;

    protected $table = 'sellers';
}

// UDE QueryBuilder over the model's table/connection.
$active = SellerWithUniversal::universal()
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->get();

// Full recordset of the table.
$all = (new SellerWithUniversal)->universalRecordset();

// Trait helpers.
$table = SellerWithUniversal::universalTable();       // 'sellers'
$connection = SellerWithUniversal::universalConnection();

// --- Global macros on the Eloquent Builder ----------------------------------

$recordset = Seller::query()->recordset();            // does not alter Eloquent results
$universal = Seller::query()->universal()->where('is_active', true)->get();

// --- Hydration --------------------------------------------------------------

$light = SellerWithUniversal::universal()->light()->first();   // stdClass

// Real Eloquent models from raw rows (factory).
$models = SellerWithUniversal::universal()
    ->models(fn (array $row) => Seller::query()->newFromBuilder($row))
    ->limit(100)
    ->get();
