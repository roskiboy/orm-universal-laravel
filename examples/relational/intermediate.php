<?php

declare(strict_types=1);

use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function relational_intermediate_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== relational/intermediate: OO expressions, joins and parameters ==\n";

$connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-intermediate');
$executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

$executor->execute(new RawQuery('create table sellers (id integer primary key autoincrement, name text not null, country text null)'));
$executor->execute(new RawQuery('create table orders (id integer primary key autoincrement, seller_id integer not null, total numeric not null, status text not null)'));
$executor->execute(new RawQuery('create index orders_seller_idx on orders (seller_id)'));

$sellers = static fn (): QueryBuilder => QueryBuilder::table($executor, 'sellers');
$orders = static fn (): QueryBuilder => QueryBuilder::table($executor, 'orders');

$sellers()->insertMany([
    ['name' => 'ana', 'country' => 'mx'],
    ['name' => 'bob', 'country' => 'us'],
    ['name' => 'carla', 'country' => null],
]);

$orders()->insertMany([
    ['seller_id' => 1, 'total' => 120, 'status' => 'paid'],
    ['seller_id' => 1, 'total' => 30, 'status' => 'refunded'],
    ['seller_id' => 2, 'total' => 80, 'status' => 'pending'],
    ['seller_id' => 3, 'total' => 200, 'status' => 'paid'],
]);

$highValue = $orders()
    ->select('seller_id', 'total')
    ->where(Column::make('total')->gte(80))
    ->where(function ($group): void {
        $group->where('status', 'paid')->orWhere('status', 'pending');
    })
    ->orderByDesc('total')
    ->get();

relational_intermediate_assert(count($highValue) === 3, 'OO group: >= 80 and (paid or pending)');
relational_intermediate_assert((float) $highValue[0]['total'] === 200.0, 'orderByDesc() sorts by total');

$inList = $orders()->whereIn('status', ['paid', 'refunded'])->count();
relational_intermediate_assert($inList === 3, 'whereIn() filters by list');

$between = $orders()->whereBetween('total', 50, 150)->count();
relational_intermediate_assert($between === 2, 'whereBetween() filters by range');

$nullCountry = $sellers()->whereNull('country')->count();
relational_intermediate_assert($nullCountry === 1, 'whereNull() detects nulls');

$like = $sellers()->where(Column::make('name')->like('a%'))->count();
relational_intermediate_assert($like === 1, 'Expression::like() filters patterns');

$joined = $orders()
    ->select('sellers.name', 'orders.total')
    ->join('sellers', 'sellers.id', '=', 'orders.seller_id')
    ->where(Column::make('orders.total')->gte(100))
    ->orderByDesc('orders.total')
    ->get();

relational_intermediate_assert(count($joined) === 2, 'join() + OO expression returns 2 rows');
relational_intermediate_assert($joined[0]['name'] === 'carla', 'the join brings the seller name');

$named = $orders()
    ->select('id', 'total')
    ->where(Column::make('total')->gt(Expression::param('min_total')))
    ->where('status', 'paid')
    ->using(['min_total' => 100])
    ->get();

relational_intermediate_assert(count($named) === 2, 'named parameter :min_total resolved with using()');

$aggregated = $orders()
    ->select('status', Column::make('total')->sum()->as('revenue'))
    ->groupBy('status')
    ->having(Column::make('total')->sum()->gte(100))
    ->orderByDesc('revenue')
    ->get();

relational_intermediate_assert(count($aggregated) === 1, 'having() filters groups by aggregate');
relational_intermediate_assert((float) $aggregated[0]['revenue'] === 320.0, 'sum() aggregates per group (paid)');

$plan = $orders()->select('id')->where('status', 'paid')->toPlan();
relational_intermediate_assert($plan['source'] === 'orders', 'toPlan() exposes the source table');
relational_intermediate_assert($plan['ast'] === 'SelectQuery', 'toPlan() reports the AST type');

echo "relational/intermediate OK\n";
exit(0);
