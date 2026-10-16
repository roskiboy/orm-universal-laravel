<?php

declare(strict_types=1);

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

$fragments = [
    'basic.php',
    'intermediate.php',
    'advanced.php',
    'expert.php',
    'extreme.php',
];

echo "== OrmUniversal\Laravel examples ==\n";
echo "These files are integration FRAGMENTS, not standalone scripts.\n";
echo "They require a Laravel app (12.x/13.x) with OrmUniversal\Laravel installed\n";
echo "and the OrmUniversalServiceProvider provider active via auto-discovery.\n\n";
echo "Installation in the app:\n";
echo "  composer require roskiboy/orm-universal-laravel\n";
echo "  php artisan ude:install\n\n";
echo "Available fragments:\n";

foreach ($fragments as $fragment) {
    echo '  - examples/'.$fragment."\n";
}

echo "\n[RUNNER] Nothing to run here; documented fragments. exit 0\n";
exit(0);
