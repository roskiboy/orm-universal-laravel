<?php

declare(strict_types=1);

$packageAutoload = __DIR__.'/../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

$fragments = [
    'basic.php',
    'intermediate.php',
    'advanced.php',
    'expert.php',
    'extreme.php',
];

echo "== orm-universal/laravel examples ==\n";
echo "Estos archivos son FRAGMENTOS de integracion, no scripts standalone.\n";
echo "Requieren una app Laravel (12.x/13.x) con orm-universal/laravel instalado\n";
echo "y el provider OrmUniversalServiceProvider activo por auto-discovery.\n\n";
echo "Instalacion en la app:\n";
echo "  composer require orm-universal/contracts orm-universal/relational orm-universal/nosql orm-universal/laravel\n";
echo "  php artisan ude:install\n\n";
echo "Fragmentos disponibles:\n";

foreach ($fragments as $fragment) {
    echo '  - examples/'.$fragment."\n";
}

echo "\n[RUNNER] Nada que ejecutar aqui; fragmentos documentados. exit 0\n";
exit(0);
