<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;
use OrmUniversal\Relational\Execution\QueryExecutor;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function relational_expert_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== relational/expert: upsert, returning, transactions and bulk ==\n";

$connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-expert');
$executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

$executor->execute(new RawQuery(
    'create table sellers (id integer primary key autoincrement, name text not null unique, balance numeric not null default 0, active integer not null default 0)',
));

$sellers = static fn (): QueryBuilder => QueryBuilder::table($executor, 'sellers');

$bulk = $sellers()->insertMany([
    ['name' => 'ana', 'balance' => 100, 'active' => 1],
    ['name' => 'bob', 'balance' => 50, 'active' => 0],
    ['name' => 'carla', 'balance' => 200, 'active' => 1],
]);
relational_expert_assert($bulk === 3, 'insertMany() inserts 3 rows');

$upserted = $sellers()->upsertMany([
    ['name' => 'ana', 'balance' => 150, 'active' => 1],
    ['name' => 'dave', 'balance' => 75, 'active' => 0],
], ['name'], ['balance', 'active']);
relational_expert_assert($upserted === 2, 'upsertMany() inserts/updates 2 rows');
relational_expert_assert((float) $sellers()->where('name', 'ana')->value('balance') === 150.0, 'upsert updated ana');
relational_expert_assert($sellers()->where('name', 'dave')->exists(), 'upsert inserted dave');
relational_expert_assert($sellers()->count() === 4, '4 rows remain after upsert');

$returned = $executor->rows(
    InsertQuery::into('sellers')
        ->values(['name' => 'erin', 'balance' => 10, 'active' => 1])
        ->returning(['id', 'name', 'balance']),
);
relational_expert_assert(count($returned) === 1 && $returned[0]['name'] === 'erin', 'returning() returns the inserted row');
relational_expert_assert(isset($returned[0]['id']), 'returning() includes the generated id');

$transaction = $executor->transaction();
$transaction->begin();
$transaction->savepoint('before_temp');
$sellers()->insert([['name' => 'temp', 'balance' => 1, 'active' => 0]]);
relational_expert_assert($sellers()->count() === 6, 'the temp row is visible inside the transaction');
$transaction->rollbackTo('before_temp');
$transaction->releaseSavepoint('before_temp');
relational_expert_assert($sellers()->count() === 5, 'rollbackTo(savepoint) undid only temp');
$transaction->commit();
relational_expert_assert($transaction->state()->value === 'committed', 'commit() leaves state committed');

$transaction2 = $executor->transaction();
$transaction2->begin();
$sellers()->insert([['name' => 'ghost', 'balance' => 9, 'active' => 0]]);
$transaction2->rollback();
relational_expert_assert(! $sellers()->where('name', 'ghost')->exists(), 'full rollback() undid the insert');

$bobId = (int) $sellers()->where('name', 'bob')->value('id');
$daveId = (int) $sellers()->where('name', 'dave')->value('id');
$erinId = (int) $sellers()->where('name', 'erin')->value('id');

$updated = $sellers()->updateMany(['active' => 1], [$bobId, $daveId]);
relational_expert_assert($updated === 2, 'updateMany() in batch updates 2 rows');
relational_expert_assert($sellers()->where('active', true)->count() === 5, '5 active sellers');

$deleted = $sellers()->deleteMany([$bobId, $erinId]);
relational_expert_assert($deleted === 2, 'deleteMany() deletes 2 rows');
relational_expert_assert($sellers()->count() === 3, '3 rows remain');

$confirmed = $sellers()
    ->usingPolicy(new DestructiveStatementPolicy(DestructiveStatementPolicy::REQUIRE_CONFIRMATION))
    ->confirmDestructive()
    ->update(['active' => 1]);
relational_expert_assert($confirmed === 3, 'UPDATE without WHERE allowed with confirmDestructive()');

$denied = false;
try {
    $sellers()
        ->usingPolicy(new DestructiveStatementPolicy(DestructiveStatementPolicy::DENY))
        ->delete();
} catch (QueryException $exception) {
    $denied = true;
    echo '[OK] DELETE without WHERE blocked by DENY policy: '.$exception->getMessage()."\n";
}
relational_expert_assert($denied, 'DestructiveStatementPolicy::DENY protects mass DELETE');

echo "relational/expert OK\n";
exit(0);
