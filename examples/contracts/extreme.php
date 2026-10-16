<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function contracts_extreme_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== contracts/extreme: negociación multi-backend + cache key ==\n";

$required = [Capability::RETURNING, Capability::CTE, Capability::JSON];

$backends = [
    'sqlite-3.49.2' => CapabilitySet::of([Capability::RETURNING, Capability::JSON, Capability::CTE]),
    'mysql-8.0' => CapabilitySet::of([Capability::CTE, Capability::JSON]),
    'postgresql-14.0' => CapabilitySet::of([Capability::RETURNING, Capability::CTE, Capability::JSON, Capability::JSONB]),
];

$matrix = [];
foreach ($backends as $engine => $capabilities) {
    [$name, $version] = explode('-', $engine, 2);
    $missing = $capabilities->missing($required);
    $matrix[$engine] = ['supported' => $missing === [], 'missing' => $missing];

    if ($missing !== []) {
        try {
            $capabilities->requireAll($required, $name, $version);
            contracts_extreme_assert(false, "requireAll() debía lanzar en {$engine}");
        } catch (UnsupportedFeatureException $exception) {
            echo "[OK] {$engine} sin [".implode(', ', $missing).']: '.$exception->feature."\n";
        }

        continue;
    }

    $capabilities->requireAll($required, $name, $version);
    echo "[OK] {$engine} soporta todas las capacidades requeridas\n";
}

contracts_extreme_assert($matrix['sqlite-3.49.2']['supported'], 'sqlite cubre el set completo');
contracts_extreme_assert($matrix['mysql-8.0']['missing'] === [Capability::RETURNING], 'mysql 8.0 carece de RETURNING');
contracts_extreme_assert($matrix['postgresql-14.0']['supported'], 'postgresql cubre el set completo');

$report = [];
foreach ($matrix as $engine => $result) {
    $report[] = $engine.': '.($result['supported'] ? 'OK' : 'faltan '.implode('+', $result['missing']));
}
contracts_extreme_assert(count($report) === 3, 'matriz de decisión generada');

$fingerprint = QueryFingerprint::fromSql(
    'select customer, sum(total) as revenue from "orders" where status = \'paid\' group by customer',
    1,
);
$cacheKey = 'ude:report:'.(string) $fingerprint;
contracts_extreme_assert(str_starts_with($cacheKey, 'ude:report:'), 'cache key derivada del fingerprint');
contracts_extreme_assert(strlen($fingerprint->hash) === 64, 'hash sha256 de 64 hex');

$sameShape = QueryFingerprint::fromSql(
    'select customer, sum(total) as revenue from "orders" where status = \'refunded\' group by customer',
    1,
);
contracts_extreme_assert($fingerprint->equals($sameShape), 'dos valores distintos comparten shape de reporte');

echo "contracts/extreme OK\n";
exit(0);
