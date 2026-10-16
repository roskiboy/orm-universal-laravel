<?php

declare(strict_types=1);

use OrmUniversal\NoSql\Document;
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function nosql_basic_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== nosql/basic: DocumentStore + InMemoryDriver ==\n";

$store = new DocumentStore;
$store->register('memory', new InMemoryDriver, default: true);

nosql_basic_assert($store->defaultDriver() === 'memory', 'driver default registrado');
nosql_basic_assert($store->drivers() === ['memory'], 'registry expone los drivers');

$orders = $store->collection('orders');
nosql_basic_assert($orders->name() === 'orders', 'collection() conserva el nombre');
nosql_basic_assert($orders->driver()->name() === 'in-memory', 'driver() devuelve el InMemory');

$ids = $orders->insertMany([
    ['customer' => 'ana', 'total' => 120, 'status' => 'paid'],
    ['customer' => 'bob', 'total' => 80, 'status' => 'pending'],
    ['customer' => 'carla', 'total' => 200, 'status' => 'paid'],
]);

nosql_basic_assert($ids === [1, 2, 3], 'insertMany() devuelve ids secuenciales');
nosql_basic_assert($orders->count() === 3, 'count() total');
nosql_basic_assert($orders->count() === 3, 'count() no consume el filtro');

$single = $store->collection('orders')->insert(['customer' => 'dave', 'total' => 10, 'status' => 'pending']);
nosql_basic_assert($single === 4, 'insert() devuelve el id de un documento');

$paid = $store->collection('orders')->where('status', 'paid')->find();
nosql_basic_assert(count($paid) === 2, 'where()->find() filtra por igualdad');
nosql_basic_assert($paid[0] instanceof Document, 'find() devuelve Documents');
nosql_basic_assert($paid[0]->get('customer') === 'ana', 'Document::get()');
nosql_basic_assert($paid[0]->customer === 'ana', 'Document::__get()');
nosql_basic_assert(isset($paid[0]['total']) && $paid[0]['total'] === 120, 'Document implementa ArrayAccess');

$first = $store->collection('orders')->where('customer', 'carla')->first();
nosql_basic_assert($first instanceof Document && $first->get('total') === 200, 'first() devuelve un documento');
nosql_basic_assert($store->collection('orders')->count() === 4, 'first() resetea el filtro pendiente');

$one = $store->driver()->findOne('orders', ['customer' => 'bob']);
nosql_basic_assert(is_array($one) && $one['status'] === 'pending', 'findOne() devuelve array crudo');

$updated = $store->collection('orders')->where('customer', 'bob')->update(['$set' => ['status' => 'paid']]);
nosql_basic_assert($updated === 1, 'update() con $set actualiza 1 documento');
nosql_basic_assert($store->collection('orders')->where('status', 'paid')->count() === 3, 'el cambio es visible');

$deleted = $store->collection('orders')->where('customer', 'dave')->delete();
nosql_basic_assert($deleted === 1, 'delete() elimina 1 documento');
nosql_basic_assert($store->collection('orders')->count() === 3, 'quedan 3 documentos');

$document = Document::fromArray(['name' => 'x', 'meta' => ['a' => 1]]);
nosql_basic_assert($document->has('meta') && ! $document->has('missing'), 'Document::has()');
nosql_basic_assert($document->toArray() === ['name' => 'x', 'meta' => ['a' => 1]], 'Document::toArray()');
nosql_basic_assert(json_encode($document) === '{"name":"x","meta":{"a":1}}', 'Document es JsonSerializable');

$orders->drop();
nosql_basic_assert($store->collection('orders')->count() === 0, 'drop() vacía la colección');

echo "nosql/basic OK\n";
exit(0);
