<?php

// FRAGMENTO de integración: requiere una app Laravel con orm-universal/laravel
// instalado. NO ejecutar standalone.

declare(strict_types=1);

use App\Models\Seller;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;

// --- Ciclo de adopción completo (12 pasos del monorepo) ---------------------

// 1. Instalación y diagnóstico (ejecutar en la app):
//    php artisan ude:install
//    php artisan ude:doctor
//    php artisan ude:compatibility --json

// 2. Analizar patrones y mapear persistencia:
//    php artisan ude:project:analyze
//    php artisan ude:map

// 3-4. Backup y modo híbrido:
//    git checkout -b ude-migration
//    config/orm-universal.php -> compatibility_mode = hybrid

// 5-6. Modelo con puente (opt-in) y análisis:
//    php artisan ude:model:inspect Seller

// 7. Sugerencias de codemod (dry-run; nunca escribe):
//    php artisan ude:migrate:codemod --diff

// 8. Validar migraciones y esquema:
//    php artisan ude:migrate:analyze
//    php artisan ude:schema:diff --snapshot=storage/ude/schema.json

// 9. Paridad Eloquent <-> UDE antes de optimizar:
$eloquent = Seller::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

$ude = array_values(OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->pluck('name'));

assert($ude === $eloquent);

// 10. Lectura caliente con streaming y política destructiva explícita:
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

// 11. Benchmark reproducible:
//     php artisan ude:benchmark sellers --rows=10000

// 12. Optimizado solo con paridad verde:
//     config/orm-universal.php -> compatibility_mode = optimized

// Recordatorio: reportes en storage/ude/ (JSON + Markdown).
$reportPath = config('orm-universal.tooling.report_path');
