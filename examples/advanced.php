<?php

// FRAGMENTO de integración: requiere una app Laravel con orm-universal/laravel
// instalado. NO ejecutar standalone.

declare(strict_types=1);

use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// --- Streaming real sobre PDO para exportaciones ----------------------------

$export = OrmUniversal::table('sales')
    ->select('id', 'seller_id', 'total', 'created_at')
    ->where(Column::make('created_at')->gte('2026-01-01'))
    ->orderBy('id')
    ->light()
    ->cursor();

foreach ($export as $row) {
    // stdClass; el cursor no re-ejecuta ni materializa todo
    $total = $row->total;
}

// chunk() entrega bloques sin re-ejecutar la consulta.
OrmUniversal::table('sales')
    ->where('status', 'pending')
    ->chunk(500, function (array $chunk, int $page): bool {
        // procesar $chunk; devolver false detiene
        return true;
    });

// Paginación length-aware.
$page = OrmUniversal::table('sellers')->orderBy('name')->paginate(25, 2);
$page->total();
$page->lastPage();

// --- Profiler y modo de compatibilidad --------------------------------------

$profiler = OrmUniversal::kernel()->profiler();
$report = OrmUniversal::kernel()->compatibility()->report();
// ['mode' => 'hybrid', 'legacy_apis_allowed' => true, 'guarantees' => [...]]

$mode = OrmUniversal::kernel()->compatibility()->mode(); // CompatibilityMode::Hybrid

// Los reportes de tooling viven en storage/ude (config orm-universal.tooling.report_path).
$reportPath = config('orm-universal.tooling.report_path');
