<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use OrmUniversal\NoSql\Driver\MongoDbDriver;
use OrmUniversal\NoSql\Driver\RedisDriver;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function nosql_extreme_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== nosql/extreme: Redis real + matriz de capacidades ==\n";

$store = new DocumentStore;
$store->register('in-memory', new InMemoryDriver, default: true);

$matrix = [
    'in-memory' => $store->driver('in-memory')->capabilities(),
];

if (MongoDbDriver::available()) {
    $matrix['mongodb'] = (new MongoDbDriver)->capabilities();
}

if (! RedisDriver::available()) {
    echo "[SKIP] Redis: se requiere ext-redis (exit 0)\n";
    exit(0);
}

try {
    $redis = new RedisDriver('127.0.0.1', 6379, 'ude_examples');
    $redis->drop('sessions_probe');
} catch (Throwable $exception) {
    echo '[SKIP] Redis no responde en 127.0.0.1:6379: '.$exception->getMessage()." (exit 0)\n";
    exit(0);
}

$matrix['redis'] = $redis->capabilities();

nosql_extreme_assert($redis->name() === 'redis', 'driver name()');
nosql_extreme_assert($redis->supports(Capability::NOSQL_KEY_VALUE), 'Redis declara key-value');
nosql_extreme_assert($redis->supports(Capability::NOSQL_TTL), 'Redis declara TTL');
nosql_extreme_assert(! $redis->supports(Capability::NOSQL_AGGREGATION), 'Redis no declara agregación');

$ids = $redis->insertMany('sessions_probe', [
    ['_id' => 's1', 'user' => 'ana', 'ttl' => 3600, 'active' => 1],
    ['_id' => 's2', 'user' => 'bob', 'ttl' => 60, 'active' => 0],
    ['_id' => 's3', 'user' => 'carla', 'ttl' => 900, 'active' => 1],
]);

nosql_extreme_assert($ids === ['s1', 's2', 's3'], 'insertMany() devuelve los _id string');
nosql_extreme_assert($redis->count('sessions_probe') === 3, 'count() total');
nosql_extreme_assert($redis->count('sessions_probe', ['active' => 1]) === 2, 'filtro local sobre documentos JSON');

$one = $redis->findOne('sessions_probe', ['_id' => 's2']);
nosql_extreme_assert(is_array($one) && $one['user'] === 'bob', 'findOne() devuelve el documento');

$updated = $redis->updateMany('sessions_probe', ['user' => 'bob'], ['$set' => ['active' => 1]]);
nosql_extreme_assert($updated === 1, 'updateMany() con $set');
nosql_extreme_assert($redis->count('sessions_probe', ['active' => 1]) === 3, 'el cambio es visible');

$unsupported = false;
try {
    $redis->aggregate('sessions_probe', Pipeline::make()->group(['_id' => '$user', 'total' => ['$sum' => '$ttl']]));
} catch (UnsupportedFeatureException $exception) {
    $unsupported = true;
    echo '[OK] Redis no simula pipelines: '.str_replace(PHP_EOL, ' | ', $exception->report())."\n";
}
nosql_extreme_assert($unsupported, 'agregación en Redis lanza UnsupportedFeatureException con alternativas');

$deleted = $redis->deleteMany('sessions_probe', ['active' => 0]);
nosql_extreme_assert($deleted === 0, 'deleteMany() no borra lo que no coincide');
$deletedActive = $redis->deleteMany('sessions_probe', ['_id' => 's3']);
nosql_extreme_assert($deletedActive === 1, 'deleteMany() borra por _id');
nosql_extreme_assert($redis->count('sessions_probe') === 2, 'quedan 2 sesiones');

$redis->drop('sessions_probe');
nosql_extreme_assert($redis->count('sessions_probe') === 0, 'drop() limpia la colección');

foreach ($matrix as $driver => $capabilities) {
    $flags = [];
    foreach ([Capability::NOSQL_AGGREGATION, Capability::NOSQL_PIPELINE, Capability::NOSQL_KEY_VALUE, Capability::NOSQL_TTL] as $capability) {
        $flags[] = $capability.'='.($capabilities->supports($capability) ? 'yes' : 'no');
    }
    echo '[CAPS] '.$driver.': '.implode(' ', $flags)."\n";
}

nosql_extreme_assert($matrix['in-memory']->supports(Capability::NOSQL_AGGREGATION), 'InMemory soporta agregación');
nosql_extreme_assert($matrix['redis']->supports(Capability::NOSQL_KEY_VALUE), 'Redis soporta key-value');

echo "nosql/extreme OK\n";
exit(0);
