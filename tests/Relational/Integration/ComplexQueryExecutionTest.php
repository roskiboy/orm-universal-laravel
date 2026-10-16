<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End to end SQLite coverage for the complex-query builder API (optimize-8):
 * subquery joins/sources, IN (subquery), EXISTS closures, aggregate HAVING,
 * window functions and recursive CTEs.
 *
 * @group integration
 */
final class ComplexQueryExecutionTest extends TestCase
{
    private QueryExecutor $executor;

    protected function setUp(): void
    {
        $connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-complex');
        $this->executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

        $this->executor->execute(new RawQuery(
            'create table sellers (id integer primary key, name text not null)',
        ));
        $this->executor->execute(new RawQuery(
            'create table orders (id integer primary key, seller_id integer not null, amount numeric not null)',
        ));
        $this->executor->execute(new RawQuery(
            'create table nodes (id integer primary key, parent_id integer null, label text)',
        ));

        QueryBuilder::table($this->executor, 'sellers')->insertMany([
            ['id' => 1, 'name' => 'ana'],
            ['id' => 2, 'name' => 'bob'],
            ['id' => 3, 'name' => 'carla'],
        ]);
        QueryBuilder::table($this->executor, 'orders')->insertMany([
            ['id' => 1, 'seller_id' => 1, 'amount' => 10],
            ['id' => 2, 'seller_id' => 1, 'amount' => 20],
            ['id' => 3, 'seller_id' => 3, 'amount' => 5],
        ]);
        QueryBuilder::table($this->executor, 'nodes')->insertMany([
            ['id' => 1, 'parent_id' => null, 'label' => 'root'],
            ['id' => 2, 'parent_id' => 1, 'label' => 'child'],
            ['id' => 3, 'parent_id' => 2, 'label' => 'grandchild'],
        ]);
    }

    public function test_select_subquery_executes_correlated_counts(): void
    {
        $rows = QueryBuilder::table($this->executor, 'sellers')
            ->select('sellers.id', 'sellers.name')
            ->selectSub(
                QueryBuilder::table($this->executor, 'orders')
                    ->select(RawExpression::trusted('count(*)'))
                    ->whereColumn('orders.seller_id', '=', 'sellers.id'),
                'orders_count',
            )
            ->orderBy('sellers.id')
            ->get();

        self::assertSame(
            [
                ['id' => 1, 'name' => 'ana', 'orders_count' => 2],
                ['id' => 2, 'name' => 'bob', 'orders_count' => 0],
                ['id' => 3, 'name' => 'carla', 'orders_count' => 1],
            ],
            array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'orders_count' => (int) $row['orders_count'],
            ], $rows),
        );
    }

    public function test_where_in_subquery_and_from_subquery_execute(): void
    {
        $sellers = QueryBuilder::table($this->executor, 'sellers')
            ->fromSubquery(
                QueryBuilder::table($this->executor, 'sellers')->select('id', 'name'),
                'active_sellers',
            )
            ->select('active_sellers.name')
            ->whereInSub('active_sellers.id', QueryBuilder::table($this->executor, 'orders')->select('seller_id')->where('amount', '>', 5))
            ->orderBy('active_sellers.name')
            ->pluck('name');

        self::assertSame(['ana'], $sellers);
    }

    public function test_having_sugar_and_or_having_execute(): void
    {
        $rows = QueryBuilder::table($this->executor, 'orders')
            ->select('seller_id')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('seller_id')
            ->having('seller_id', '>=', 1)
            ->orHaving('seller_id', '=', 99)
            ->orderBy('seller_id')
            ->get();

        self::assertSame([1, 3], array_map(static fn (array $row): int => (int) $row['seller_id'], $rows));
    }

    public function test_raw_helpers_keep_bindings_and_execute(): void
    {
        $rows = QueryBuilder::table($this->executor, 'orders')
            ->select('seller_id')
            ->selectRaw('SUM(amount) as total')
            ->whereRaw('amount >= ?', [5])
            ->groupByRaw('seller_id')
            ->havingRaw('SUM(amount) >= ?', [5])
            ->orderByRaw('SUM(amount)', [], 'desc')
            ->get();

        self::assertSame(
            [
                ['seller_id' => 1, 'total' => '30'],
                ['seller_id' => 3, 'total' => '5'],
            ],
            array_map(static fn (array $row): array => [
                'seller_id' => (int) $row['seller_id'],
                'total' => (string) $row['total'],
            ], $rows),
        );
    }

    public function test_exists_closure_and_not_exists_execute(): void
    {
        $withOrders = QueryBuilder::table($this->executor, 'sellers')
            ->whereExists(function (QueryBuilder $sub): void {
                $sub->fromTable('orders')->whereColumn('orders.seller_id', '=', 'sellers.id');
            })
            ->orderBy('id')
            ->pluck('name');

        self::assertSame(['ana', 'carla'], $withOrders);

        $withoutOrders = QueryBuilder::table($this->executor, 'sellers')
            ->whereNotExists(function (QueryBuilder $sub): void {
                $sub->fromTable('orders')->whereColumn('orders.seller_id', '=', 'sellers.id');
            })
            ->pluck('name');

        self::assertSame(['bob'], $withoutOrders);
    }

    public function test_left_join_subquery_executes(): void
    {
        $totals = QueryBuilder::table($this->executor, 'sellers')
            ->select('sellers.name')
            ->addSelect(RawExpression::parameterized('COALESCE(agg.total, ?)', [0])->as('total'))
            ->leftJoinSub(
                QueryBuilder::table($this->executor, 'orders')
                    ->select('orders.seller_id')
                    ->selectRaw('SUM(amount) as total')
                    ->groupBy('orders.seller_id'),
                'agg',
                'agg.seller_id',
                '=',
                'sellers.id',
            )
            ->orderBy('sellers.id')
            ->get();

        self::assertSame(
            [
                ['name' => 'ana', 'total' => '30'],
                ['name' => 'bob', 'total' => '0'],
                ['name' => 'carla', 'total' => '5'],
            ],
            array_map(static fn (array $row): array => [
                'name' => $row['name'],
                'total' => (string) $row['total'],
            ], $totals),
        );
    }

    public function test_window_function_executes_on_sqlite(): void
    {
        $rows = QueryBuilder::table($this->executor, 'orders')
            ->select('id', 'seller_id')
            ->selectRaw('SUM(amount) over (partition by seller_id order by id rows between unbounded preceding and current row) as running')
            ->orderBy('id')
            ->get();

        self::assertSame(
            [
                ['id' => 1, 'running' => '10'],
                ['id' => 2, 'running' => '30'],
                ['id' => 3, 'running' => '5'],
            ],
            array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'running' => (string) $row['running'],
            ], $rows),
        );
    }

    public function test_recursive_cte_executes(): void
    {
        $rows = QueryBuilder::table($this->executor, 'tree')
            ->withRecursive(
                'tree',
                QueryBuilder::table($this->executor, 'nodes')
                    ->fromTable('nodes', 'n')
                    ->select('n.id', 'n.parent_id')
                    ->whereNull('n.parent_id')
                    ->unionAll(
                        QueryBuilder::table($this->executor, 'nodes')
                            ->fromTable('nodes', 'c')
                            ->select('c.id', 'c.parent_id')
                            ->join('tree', 'c.parent_id', '=', 'tree.id'),
                    ),
            )
            ->select('tree.id')
            ->orderBy('tree.id')
            ->pluck('id');

        self::assertSame([1, 2, 3], array_map(static fn (mixed $id): int => (int) $id, $rows));
    }
}
