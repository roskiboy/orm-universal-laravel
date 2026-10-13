<?php

// Integration FRAGMENT: requires a Laravel app with orm-universal/laravel
// installed. Do NOT run standalone.

declare(strict_types=1);

use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// --- Real streaming over PDO for exports ------------------------------------

$export = OrmUniversal::table('sales')
    ->select('id', 'seller_id', 'total', 'created_at')
    ->where(Column::make('created_at')->gte('2026-01-01'))
    ->orderBy('id')
    ->light()
    ->cursor();

foreach ($export as $row) {
    // stdClass; the cursor does not re-execute or materialize everything
    $total = $row->total;
}

// chunk() delivers blocks without re-running the query.
OrmUniversal::table('sales')
    ->where('status', 'pending')
    ->chunk(500, function (array $chunk, int $page): bool {
        // process $chunk; returning false stops
        return true;
    });

// Length-aware pagination.
$page = OrmUniversal::table('sellers')->orderBy('name')->paginate(25, 2);
$page->total();
$page->lastPage();

// --- Profiler and compatibility mode ----------------------------------------

$profiler = OrmUniversal::kernel()->profiler();
$report = OrmUniversal::kernel()->compatibility()->report();
// ['mode' => 'hybrid', 'legacy_apis_allowed' => true, 'guarantees' => [...]]

$mode = OrmUniversal::kernel()->compatibility()->mode(); // CompatibilityMode::Hybrid

// Tooling reports live in storage/ude (config orm-universal.tooling.report_path).
$reportPath = config('orm-universal.tooling.report_path');
