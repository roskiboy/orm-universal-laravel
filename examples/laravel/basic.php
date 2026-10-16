<?php

// Integration FRAGMENT: requires a Laravel app with OrmUniversal\Laravel
// installed. Do NOT run with `php basic.php` (no container/config).

declare(strict_types=1);

use App\Models\Seller;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// --- Vanilla Eloquent: intact, unchanged ------------------------------------

$eloquent = Seller::query()
    ->where('is_active', true)
    ->orderBy('name')
    ->pluck('name');

// --- The same intent through the universal engine ---------------------------

$ude = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->pluck('name');

// Equivalent results in hybrid mode (parity verified by tests).
assert(array_values($ude) === $eloquent->all());

// Recordset zero hydration: arrays, not models.
$recordset = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();

foreach ($recordset as $seller) {
    // $seller is an associative array
    $name = $seller['name'];
}

// No soft delete magic: if the model uses SoftDeletes, declare it.
$notTrashed = OrmUniversal::table('sellers')
    ->whereNull('deleted_at')
    ->count();

// Explicit connection when the app has several.
$fromReports = OrmUniversal::table('sellers', 'reports')
    ->limit(50)
    ->get();
