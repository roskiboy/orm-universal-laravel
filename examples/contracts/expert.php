<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Exceptions\DialectException;
use OrmUniversal\Contracts\Exceptions\PersistenceException;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function contracts_expert_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== contracts/expert: errores ricos y reporte ==\n";

$exception = UnsupportedFeatureException::forFeature(
    feature: Capability::NOSQL_AGGREGATION,
    engine: 'redis',
    engineVersion: '5.0.14',
    reason: 'Redis is a key-value store: it has no aggregation pipeline',
    alternatives: [
        'Retrieve the documents and aggregate in the application layer',
        'Use MongoDB when aggregation is required',
    ],
);

contracts_expert_assert($exception->feature === 'supportsAggregation', 'feature queda disponible');
contracts_expert_assert($exception->engine === 'redis', 'engine queda disponible');
contracts_expert_assert($exception->engineVersion === '5.0.14', 'engineVersion queda disponible');
contracts_expert_assert(count($exception->alternatives) === 2, 'alternatives queda disponible');
contracts_expert_assert($exception instanceof DialectException, 'hereda de DialectException');
contracts_expert_assert($exception instanceof PersistenceException, 'hereda de PersistenceException');

$report = $exception->report();
contracts_expert_assert(str_contains($report, 'Feature: supportsAggregation'), 'report() incluye la feature');
contracts_expert_assert(str_contains($report, 'Database: redis'), 'report() incluye el motor');
contracts_expert_assert(str_contains($report, '1. Retrieve the documents'), 'report() numera las alternativas');

$base = new PersistenceException('Fallo de persistencia', 7, null, ['table' => 'orders']);
$contextual = $base->withContext(['connection' => 'cache']);
contracts_expert_assert(
    $contextual->context() === ['table' => 'orders', 'connection' => 'cache'],
    'withContext() devuelve copia con contexto (PersistenceException)',
);

$thrown = false;
try {
    CapabilitySet::of([Capability::RETURNING])->requireAll(
        [Capability::JSONB],
        'mysql',
        '8.0',
        'MySQL 8.0 has no JSONB type',
    );
} catch (UnsupportedFeatureException $caught) {
    $thrown = true;
    contracts_expert_assert(str_contains($caught->report(), 'MySQL 8.0 has no JSONB type'), 'requireAll() propaga el motivo');
}
contracts_expert_assert($thrown, 'requireAll() lanza cuando falta una capacidad');

contracts_expert_assert($base->context() === ['table' => 'orders'], 'PersistenceException acepta contexto');
contracts_expert_assert($base->getCode() === 7, 'PersistenceException conserva el código');

echo "contracts/expert OK\n";
exit(0);
