<?php

declare(strict_types=1);

use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use OrmUniversal\NoSql\Filter;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function nosql_intermediate_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== nosql/intermediate: filtros ==\n";

$driver = new InMemoryDriver;
$store = new DocumentStore;
$store->register('memory', $driver, default: true);

$users = $store->collection('users');
$users->insertMany([
    ['name' => 'ana', 'age' => 30, 'city' => 'mx', 'meta' => ['level' => 5], 'tags' => ['a', 'b']],
    ['name' => 'bob', 'age' => 25, 'city' => 'mx', 'meta' => ['level' => 1]],
    ['name' => 'carla', 'age' => 40, 'city' => 'us', 'meta' => ['level' => 9]],
    ['name' => 'zoe', 'age' => 17, 'city' => 'us'],
]);

nosql_intermediate_assert($store->collection('users')->where('city', 'mx')->count() === 2, 'where(field, value) aplica eq');
nosql_intermediate_assert($store->collection('users')->where('age', '>=', 30)->count() === 2, 'where(field, >=, value)');
nosql_intermediate_assert($store->collection('users')->where('age', 'lt', 18)->count() === 1, 'where(field, lt, value)');
nosql_intermediate_assert($store->collection('users')->where('city', '!=', 'mx')->count() === 2, 'where(field, !=, value)');
nosql_intermediate_assert($store->collection('users')->where('name', 'in', ['ana', 'zoe'])->count() === 2, 'where(field, in, [...])');
nosql_intermediate_assert($store->collection('users')->where('tags', 'exists', true)->count() === 1, 'where(field, exists, true)');
nosql_intermediate_assert($store->collection('users')->where('name', 'regex', '^c')->count() === 1, 'where(field, regex, pattern)');

$orFilter = Filter::make()->or(
    Filter::make()->eq('city', 'mx'),
    Filter::make()->eq('age', 40),
);
nosql_intermediate_assert($driver->count('users', $orFilter->toArray()) === 3, 'Filter::or() con subfiltros');

$dotNotation = $driver->count('users', ['meta.level' => ['$gte' => 5]]);
nosql_intermediate_assert($dotNotation === 2, 'dot notation en campos anidados');

$range = Filter::make()->and(['age' => ['$gte' => 25, '$lt' => 45]]);
nosql_intermediate_assert($driver->count('users', $range->toArray()) === 3, 'rango con un solo documento de operadores');

$gotcha = Filter::make()->gte('age', 25)->lt('age', 45);
nosql_intermediate_assert($gotcha->toArray() === ['age' => ['$lt' => 45]], 'gotcha real: el segundo operador del mismo campo sobrescribe al primero');

$filtered = $store->collection('users');
$filtered->filter()->gte('age', 25);
nosql_intermediate_assert($filtered->count() === 3, 'Collection::filter() configura y count() consume el filtro');

$incremented = $store->collection('users')->where('name', 'bob')->update(['$inc' => ['age' => 5]]);
nosql_intermediate_assert($incremented === 1, 'update() con $inc');
nosql_intermediate_assert((int) $driver->findOne('users', ['name' => 'bob'])['age'] === 30, '$inc sumó 5 al campo');

$replaced = $store->collection('users')->where('name', 'zoe')->update(['name' => 'zoe', 'age' => 18, 'city' => 'us']);
nosql_intermediate_assert($replaced === 1, 'update() con reemplazo plano');
nosql_intermediate_assert((int) $driver->findOne('users', ['name' => 'zoe'])['age'] === 18, 'el reemplazo cambió el valor');

$seen = 0;
$store->collection('users')->where('city', 'mx')->each(function ($document) use (&$seen): void {
    $seen++;
});
nosql_intermediate_assert($seen === 2, 'each() recorre el filtro aplicado');

$deleted = $store->collection('users')->where('age', 'lt', 20)->delete();
nosql_intermediate_assert($deleted === 1, 'delete() con filtro de edad');
nosql_intermediate_assert($store->collection('users')->count() === 3, 'quedan 3 usuarios');

$driver->drop('users');
nosql_intermediate_assert($driver->collections() === [], 'drop() limpia la colección');

echo "nosql/intermediate OK\n";
exit(0);
