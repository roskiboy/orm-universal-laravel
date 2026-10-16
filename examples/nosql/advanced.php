<?php

declare(strict_types=1);

use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function nosql_advanced_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== nosql/advanced: aggregation pipelines ==\n";

$driver = new InMemoryDriver;
$store = new DocumentStore;
$store->register('memory', $driver, default: true);

$store->collection('orders')->insertMany([
    ['customer' => 'ana', 'total' => 100, 'status' => 'paid'],
    ['customer' => 'ana', 'total' => 50, 'status' => 'paid'],
    ['customer' => 'bob', 'total' => 30, 'status' => 'pending'],
    ['customer' => 'carla', 'total' => 200, 'status' => 'paid'],
    ['customer' => 'carla', 'total' => 20, 'status' => 'refunded'],
]);

$revenue = $store->collection('orders')->aggregate(
    Pipeline::make()
        ->match(['status' => 'paid'])
        ->group(['_id' => '$customer', 'revenue' => ['$sum' => '$total'], 'orders' => ['$count' => 1]])
        ->sort(['revenue' => -1]),
);

nosql_advanced_assert(count($revenue) === 2, 'match + group produce 2 clientes con pagos');
nosql_advanced_assert($revenue[0]['_id'] === 'carla', 'sort descendente coloca a carla primero');
nosql_advanced_assert((float) $revenue[0]['revenue'] === 200.0, '$sum acumula el revenue');
nosql_advanced_assert($revenue[1]['orders'] === 2, '$count cuenta documentos por grupo');

$avg = $store->collection('orders')->aggregate(
    Pipeline::make()->group(['_id' => '$status', 'avg_total' => ['$avg' => '$total']]),
);
nosql_advanced_assert(count($avg) === 3, 'group por status produce 3 grupos');
nosql_advanced_assert(is_float($avg[0]['avg_total']) || is_int($avg[0]['avg_total']), '$avg devuelve numérico');

$unwound = $driver->aggregate('tags_probe', Pipeline::make()->match(['_id' => 'x']));
nosql_advanced_assert($unwound === [], 'aggregate en colección vacía devuelve []');

$driver->insertMany('items', [
    ['_id' => 'a', 'name' => 'alpha', 'tags' => ['x', 'y']],
    ['_id' => 'b', 'name' => 'beta', 'tags' => ['z']],
]);

$flat = $driver->aggregate('items', Pipeline::make()->unwind('$tags')->project(['name' => 1, 'tags' => 1]));
nosql_advanced_assert(count($flat) === 3, 'unwind() expande los tags');

$page = $driver->aggregate('items', Pipeline::make()->sort(['name' => 1])->skip(1)->limit(1)->project(['name' => 1]));
nosql_advanced_assert(count($page) === 1 && $page[0]['name'] === 'beta', 'sort + skip + limit pagina el pipeline');

$custom = $driver->aggregate('items', Pipeline::make()->stage(['$match' => ['name' => 'alpha']])->project(['name' => 1]));
nosql_advanced_assert(count($custom) === 1, 'stage() acepta etapas nativas');

$pendingFilter = $store->collection('orders')->where('status', 'paid')->aggregate(
    Pipeline::make()->group(['_id' => null, 'revenue' => ['$sum' => '$total']]),
);
nosql_advanced_assert((float) $pendingFilter[0]['revenue'] === 350.0, 'el where pendiente se antepone como $match');

$stages = Pipeline::make()->match(['status' => 'paid'])->group(['_id' => '$customer'])->stages();
nosql_advanced_assert(count($stages) === 2 && array_key_first($stages[0]) === '$match', 'stages() expone las etapas');
nosql_advanced_assert(json_encode(Pipeline::make()->limit(5)) === '[{"$limit":5}]', 'Pipeline es JsonSerializable');

$driver->drop('items');
$driver->drop('orders');
nosql_advanced_assert($driver->collections() === [], 'drop() limpia todas las colecciones usadas');

echo "nosql/advanced OK\n";
exit(0);
