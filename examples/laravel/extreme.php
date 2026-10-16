<?php

// Integration FRAGMENT: requires a Laravel app with OrmUniversal\Laravel
// installed. Do NOT run standalone.

declare(strict_types=1);

use App\Models\Seller;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;

// --- Full adoption cycle (12 steps from the monorepo) -----------------------

// 1. Installation and diagnostics (run in the app):
//    php artisan ude:install
//    php artisan ude:doctor
//    php artisan ude:compatibility --json

// 2. Analyze patterns and map persistence:
//    php artisan ude:project:analyze
//    php artisan ude:map

// 3-4. Backup and hybrid mode:
//    git checkout -b ude-migration
//    config/orm-universal.php -> compatibility_mode = hybrid

// 5-6. Bridged model (opt-in) and analysis:
//    php artisan ude:model:inspect Seller

// 7. Codemod suggestions (dry-run; never writes):
//    php artisan ude:migrate:codemod --diff

// 8. Validate migrations and schema:
//    php artisan ude:migrate:analyze
//    php artisan ude:schema:diff --snapshot=storage/ude/schema.json

// 9. Eloquent <-> UDE parity before optimizing:
$eloquent = Seller::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

$ude = array_values(OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->pluck('name'));

assert($ude === $eloquent);

// 10. Hot read with streaming and explicit destructive policy:
$hot = OrmUniversal::table('sellers')
    ->where('is_active', true)
    ->light()
    ->cursor();

foreach ($hot as $seller) {
    $name = $seller->name;
}

$archived = OrmUniversal::table('sellers')
    ->where('id', 42)
    ->usingPolicy(new DestructiveStatementPolicy(DestructiveStatementPolicy::REQUIRE_CONFIRMATION))
    ->confirmDestructive()
    ->update(['is_active' => false]);

// 11. Reproducible benchmark:
//     php artisan ude:benchmark sellers --rows=10000

// 12. Optimized only with green parity:
//     config/orm-universal.php -> compatibility_mode = optimized

// Reminder: reports in storage/ude/ (JSON + Markdown).
$reportPath = config('orm-universal.tooling.report_path');
