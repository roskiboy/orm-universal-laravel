<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Driver\MongoDbDriver;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function nosql_expert_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== nosql/expert: MongoDB real (skip honesto si no está disponible) ==\n";

if (! MongoDbDriver::available()) {
    echo "[SKIP] MongoDB: se requiere ext-mongodb + mongodb/mongodb (exit 0)\n";
    exit(0);
}

try {
    $mongo = new MongoDbDriver('mongodb://127.0.0.1:27017', 'ude_examples');
    $mongo->drop('orders_probe');
} catch (Throwable $exception) {
    echo '[SKIP] MongoDB no responde en 127.0.0.1:27017: '.$exception->getMessage()." (exit 0)\n";
    exit(0);
}

nosql_expert_assert($mongo->name() === 'mongodb', 'driver name()');
nosql_expert_assert($mongo->supports(Capability::NOSQL_PIPELINE), 'MongoDB declara pipelines');
nosql_expert_assert($mongo->supports(Capability::NOSQL_TRANSACTIONS), 'MongoDB declara transacciones');
nosql_expert_assert(! $mongo->supports(Capability::NOSQL_VECTOR_SEARCH), 'Vector Search Atlas-only no se declara');

$ids = $mongo->insertMany('orders_probe', [
    ['_id' => 1, 'customer' => 'ana', 'total' => 120, 'status' => 'paid'],
    ['_id' => 2, 'customer' => 'bob', 'total' => 80, 'status' => 'pending'],
    ['_id' => 3, 'customer' => 'ana', 'total' => 30, 'status' => 'paid'],
]);

nosql_expert_assert($ids === [1, 2, 3], 'insertMany() devuelve los _id int');
nosql_expert_assert($mongo->count('orders_probe') === 3, 'count() total en Mongo');
nosql_expert_assert($mongo->count('orders_probe', ['status' => 'paid']) === 2, 'count() con filtro nativo');

$paid = $mongo->find('orders_probe', ['status' => 'paid']);
nosql_expert_assert(count($paid) === 2, 'find() sin opciones (cursor compatible)');
nosql_expert_assert($paid[0]->get('customer') === 'ana', 'find() devuelve Documents');

$one = $mongo->findOne('orders_probe', ['_id' => 2]);
nosql_expert_assert(is_array($one) && $one['customer'] === 'bob', 'findOne() devuelve array');

$aggregated = $mongo->aggregate('orders_probe', Pipeline::make()
    ->match(['status' => 'paid'])
    ->group(['_id' => '$customer', 'revenue' => ['$sum' => '$total'], 'orders' => ['$sum' => 1]])
    ->sort(['revenue' => -1]));

nosql_expert_assert(count($aggregated) === 1, 'pipeline nativo agrupa en el servidor');
nosql_expert_assert($aggregated[0]['_id'] === 'ana', '$group por customer');
nosql_expert_assert((float) $aggregated[0]['revenue'] === 150.0, '$sum nativo de Mongo');

$updated = $mongo->updateMany('orders_probe', ['customer' => 'bob'], ['status' => 'paid']);
nosql_expert_assert($updated === 1, 'updateMany() con reemplazo plano (driver lo envuelve en $set)');
nosql_expert_assert($mongo->count('orders_probe', ['status' => 'paid']) === 3, 'el update es visible');

$deleted = $mongo->deleteMany('orders_probe', ['_id' => 3]);
nosql_expert_assert($deleted === 1, 'deleteMany() elimina por _id');
nosql_expert_assert($mongo->count('orders_probe') === 2, 'quedan 2 documentos');

$mongo->drop('orders_probe');
nosql_expert_assert($mongo->count('orders_probe') === 0, 'drop() vacía la colección');

echo "nosql/expert OK\n";
exit(0);
