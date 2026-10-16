<?php

declare(strict_types=1);

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

$examples = [
    'basic.php',
    'intermediate.php',
    'advanced.php',
    'expert.php',
    'extreme.php',
];

$failed = [];

foreach ($examples as $example) {
    $path = __DIR__.'/'.$example;

    if (! is_file($path)) {
        $failed[] = $example.' (no existe)';
        echo "[RUNNER] falta {$example}\n";

        continue;
    }

    echo "== {$example} ==\n";
    $exitCode = 0;
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($path), $exitCode);
    echo "\n";

    if ($exitCode !== 0) {
        $failed[] = $example.' (exit '.$exitCode.')';
    }
}

if ($failed !== []) {
    fwrite(STDERR, '[RUNNER] fallaron: '.implode(', ', $failed)."\n");
    exit(1);
}

echo "[RUNNER] 5/5 ejemplos de nosql con exit 0 (los drivers no disponibles se saltan con aviso)\n";
exit(0);
