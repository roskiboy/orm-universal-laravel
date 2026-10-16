<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Relation\RelationDefinition;
use OrmUniversal\Relational\Relation\RelationRegistry;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Relation aggregates, EXISTS filters, builder aggregates and Eloquent-style
 * helpers against a real SQLite database.
 *
 * @group integration
 */
final class RelationAggregateTest extends TestCase
{
    private QueryExecutor $executor;

    private RelationRegistry $relations;

    protected function setUp(): void
    {
        $this->executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'agg-test'),
            SqliteDialect::make('3.49.2'),
        );

        $this->executor->execute(new RawQuery('create table sellers (id integer primary key, name text not null, active integer not null default 1)'));
        $this->executor->execute(new RawQuery('create table orders (id integer primary key, seller_id integer not null, total decimal(10,2) not null, status text not null)'));
        $this->executor->execute(new RawQuery('create table counter_probe (id integer primary key, hits integer not null default 0)'));

        $this->executor->execute(new RawQuery('insert into sellers (id, name) values (1, ?), (2, ?), (3, ?)', ['ana', 'bob', 'carla']));
        $this->executor->execute(new RawQuery("insert into orders (id, seller_id, total, status) values (1,1,100,'paid'),(2,1,50,'pending'),(3,2,30,'paid')"));

        $this->relations = new RelationRegistry;
        $this->relations->register('sellers', RelationDefinition::hasMany('orders', TableIdentifier::fromString('orders'), 'seller_id'));
    }

    private function sellers(): QueryBuilder
    {
        return QueryBuilder::table($this->executor, 'sellers')->usingRelations($this->relations);
    }

    public function test_with_count_and_exists_match_eloquent_semantics(): void
    {
        $rows = $this->sellers()
            ->withCount('orders')
            ->withExists('orders')
            ->orderBy('id')
            ->get();

        self::assertSame(2, (int) $rows[0]['orders_count']);
        self::assertSame(1, (int) $rows[1]['orders_count']);
        self::assertSame(0, (int) $rows[2]['orders_count']);
        self::assertSame(1, (int) $rows[0]['orders_exists']);
        self::assertSame(0, (int) $rows[2]['orders_exists']);
    }

    public function test_with_sum_avg_min_max_and_constraints(): void
    {
        $rows = $this->sellers()
            ->withSum('orders', 'total')
            ->withAvg('orders', 'total')
            ->withMin('orders', 'total')
            ->withMax('orders', 'total')
            ->withCount(['orders' => fn ($query) => $query->where('status', 'paid')])
            ->orderBy('id')
            ->get();

        self::assertSame(150, (int) $rows[0]['orders_sum_total']);
        self::assertSame(75, (int) $rows[0]['orders_avg_total']);
        self::assertSame(50, (int) $rows[0]['orders_min_total']);
        self::assertSame(100, (int) $rows[0]['orders_max_total']);
        self::assertSame(1, (int) $rows[0]['orders_count']);
        self::assertNull($rows[2]['orders_sum_total'], 'Empty to-many aggregates are null, like Eloquent.');
    }

    public function test_where_has_and_doesnt_have(): void
    {
        $withPaid = $this->sellers()->whereHas('orders', fn ($query) => $query->where('status', 'paid'))->orderBy('id')->pluck('name');
        self::assertSame(['ana', 'bob'], array_values($withPaid));

        $withoutOrders = $this->sellers()->doesntHave('orders')->pluck('name');
        self::assertSame(['carla'], array_values($withoutOrders));

        $none = $this->sellers()->whereHas('orders', fn ($query) => $query->where('status', 'refunded'))->count();
        self::assertSame(0, $none);
    }

    public function test_builder_aggregates(): void
    {
        $orders = QueryBuilder::table($this->executor, 'orders');

        self::assertSame(180, (int) $orders->sum('total'));
        self::assertSame(60, (int) $orders->avg('total'));
        self::assertSame(30, (int) $orders->min('total'));
        self::assertSame(100, (int) $orders->max('total'));

        self::assertSame(150, (int) QueryBuilder::table($this->executor, 'orders')->where('seller_id', 1)->sum('total'));
    }

    public function test_keyset_iteration_and_each_by_id(): void
    {
        $ids = [];
        QueryBuilder::table($this->executor, 'orders')->chunkById(2, function (array $chunk) use (&$ids): void {
            foreach ($chunk as $row) {
                $ids[] = (int) $row['id'];
            }
        });

        self::assertSame([1, 2, 3], $ids);

        $lazy = [];
        foreach (QueryBuilder::table($this->executor, 'orders')->lazyById(2) as $row) {
            $lazy[] = (int) $row['id'];
        }
        self::assertSame([1, 2, 3], $lazy);
    }

    public function test_first_or_create_update_or_create_increment(): void
    {
        $created = $this->sellers()->firstOrCreate(['name' => 'dave'], ['active' => 1]);
        self::assertSame('dave', $created['name']);
        self::assertSame(4, QueryBuilder::table($this->executor, 'sellers')->count());

        $again = $this->sellers()->firstOrCreate(['name' => 'dave']);
        self::assertSame($created['id'], $again['id']);

        $updated = $this->sellers()->updateOrCreate(['name' => 'dave'], ['active' => 0]);
        self::assertSame(0, (int) QueryBuilder::table($this->executor, 'sellers')->where('name', 'dave')->value('active'));

        QueryBuilder::table($this->executor, 'counter_probe')->insert([['id' => 1, 'hits' => 10]]);
        QueryBuilder::table($this->executor, 'counter_probe')->where('id', 1)->increment('hits', 5);
        self::assertSame(15, (int) QueryBuilder::table($this->executor, 'counter_probe')->where('id', 1)->value('hits'));
        QueryBuilder::table($this->executor, 'counter_probe')->where('id', 1)->decrement('hits', 3);
        self::assertSame(12, (int) QueryBuilder::table($this->executor, 'counter_probe')->where('id', 1)->value('hits'));

        self::assertNotNull($updated);
    }

    public function test_relation_aggregate_golden_sql(): void
    {
        $sql = $this->sellers()->withCount('orders')->sql();

        self::assertSame(
            'select *, (select COUNT(*) from "orders" where "orders"."seller_id" = "sellers"."id") as "orders_count" from "sellers"',
            $sql,
        );

        $existsSql = $this->sellers()->withExists('orders')->sql();
        self::assertSame(
            'select *, exists (select 1 from "orders" where "orders"."seller_id" = "sellers"."id") as "orders_exists" from "sellers"',
            $existsSql,
        );
    }
}
