<?php

declare(strict_types=1);

use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function relational_basic_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== relational/basic: PDO SQLite + QueryExecutor ==\n";

$connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-basic');
$executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

$executor->execute(new RawQuery(
    'create table sellers (id integer primary key autoincrement, name text not null unique, is_active integer not null default 0, balance numeric not null default 0)',
));

$sellers = static fn (): QueryBuilder => QueryBuilder::table($executor, 'sellers');

$inserted = $sellers()->insert([
    ['name' => 'ana', 'is_active' => 1, 'balance' => 120],
    ['name' => 'bob', 'is_active' => 0, 'balance' => 80],
    ['name' => 'carla', 'is_active' => 1, 'balance' => 45],
]);

relational_basic_assert($inserted === 3, 'insert() reports 3 rows');
relational_basic_assert($sellers()->count() === 3, 'count() returns 3');
relational_basic_assert($sellers()->where('name', 'ana')->exists(), 'exists() finds ana');
relational_basic_assert(! $sellers()->where('name', 'zoe')->exists(), 'exists() does not find zoe');

$names = $sellers()->select('name')->where('is_active', true)->orderBy('name')->pluck('name');
relational_basic_assert($names === ['ana', 'carla'], 'pluck() respects WHERE + ORDER BY');

$first = $sellers()->where('name', 'bob')->first();
relational_basic_assert(is_array($first) && $first['name'] === 'bob', 'first() returns the row as an array (zero hydration)');

$balance = $sellers()->where('name', 'ana')->value('balance');
relational_basic_assert((float) $balance === 120.0, 'value() returns a scalar column');

$sql = $sellers()->where('is_active', true)->sql();
relational_basic_assert(str_contains($sql, '"sellers"'), 'sql() quotes identifiers: '.$sql);
relational_basic_assert($sellers()->where('is_active', true)->bindings() === [true], 'bindings() travels separately from the SQL');

$updated = $sellers()->where('name', 'bob')->update(['is_active' => 1]);
relational_basic_assert($updated === 1, 'update() with WHERE affects 1 row');

$deleted = $sellers()->where('name', 'bob')->delete();
relational_basic_assert($deleted === 1, 'delete() with WHERE affects 1 row');
relational_basic_assert($sellers()->count() === 2, '2 rows remain');

$ping = $connection->ping();
relational_basic_assert($ping, 'ping() responds on the SQLite connection');
relational_basic_assert($executor->dialect()->name() === 'sqlite-3.49.2', 'versioned dialect active');

echo "relational/basic OK\n";
exit(0);
