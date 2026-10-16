<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Dialect;

use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\Subquery;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Dialect\MariaDbDialect;
use OrmUniversal\Relational\Dialect\PostgreSqlDialect;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use PHPUnit\Framework\TestCase;

/**
 * Golden SQL for the complex-query builder API added in optimize-8:
 * subquery joins/sources/projections, IN (subquery), aggregate HAVING,
 * named windows, window frames, recursive CTEs and typed raw fragments.
 */
final class ComplexQueryGoldenTest extends TestCase
{
    private function mariadb(): AbstractDialect
    {
        return MariaDbDialect::make('11.6.2');
    }

    private function pgsql(): AbstractDialect
    {
        return PostgreSqlDialect::make('18.0');
    }

    private function sqlite(): AbstractDialect
    {
        return SqliteDialect::make('3.49.2');
    }

    private function compile(AbstractDialect $dialect, SelectQuery $query): CompiledQuery
    {
        return $dialect->compile($query);
    }

    public function test_join_to_subquery_requires_alias_and_compiles(): void
    {
        $query = SelectQuery::make('bank_movements', 'm')
            ->select('m.id', 'h.paid_sort_order')
            ->joinSub(
                SelectQuery::make('bank_movement_histories', 'h2')
                    ->select('h2.source_movement_id', 'h2.paid_sort_order'),
                'h',
                'h.source_movement_id',
                '=',
                'm.id',
            );

        self::assertSame(
            'select `m`.`id`, `h`.`paid_sort_order` from `bank_movements` as `m`'
            .' join (select `h2`.`source_movement_id`, `h2`.`paid_sort_order` from `bank_movement_histories` as `h2`) as `h`'
            .' on `h`.`source_movement_id` = `m`.`id`',
            $this->compile($this->mariadb(), $query)->sql,
        );
        self::assertSame(
            'select "m"."id", "h"."paid_sort_order" from "bank_movements" as "m"'
            .' join (select "h2"."source_movement_id", "h2"."paid_sort_order" from "bank_movement_histories" as "h2") as "h"'
            .' on "h"."source_movement_id" = "m"."id"',
            $this->compile($this->pgsql(), $query)->sql,
        );
    }

    public function test_derived_from_with_in_subquery(): void
    {
        $query = SelectQuery::make()
            ->from(
                SelectQuery::make('treasury_movements')->select('id', 'reference')->whereNull('deleted_at'),
                'active_rows',
            )
            ->select('active_rows.id')
            ->whereIn('active_rows.id', SelectQuery::make('treasury_movements')->select('id')->where('class', 'INGRESO'));

        $compiled = $this->compile($this->sqlite(), $query);

        self::assertSame(
            'select "active_rows"."id" from (select "id", "reference" from "treasury_movements" where "deleted_at" is null) as "active_rows"'
            .' where "active_rows"."id" in (select "id" from "treasury_movements" where "class" = ?)',
            $compiled->sql,
        );
        self::assertSame(['INGRESO'], $compiled->bindings);
    }

    public function test_select_subquery_with_aggregate_having_and_or_having(): void
    {
        $query = SelectQuery::make('sellers')
            ->select('sellers.id')
            ->addSelect(
                (new Subquery(
                    SelectQuery::make('orders')
                        ->select(RawExpression::trusted('count(*)'))
                        ->whereColumn('orders.seller_id', '=', 'sellers.id'),
                ))->as('orders_count'),
            )
            ->groupBy('sellers.id')
            ->having(Column::make('sellers.id')->sum()->gte(10))
            ->orHaving(Column::make('sellers.id')->lt(3));

        $compiled = $this->compile($this->pgsql(), $query);

        self::assertSame(
            'select "sellers"."id", (select count(*) from "orders" where "orders"."seller_id" = "sellers"."id") as "orders_count"'
            .' from "sellers" group by "sellers"."id" having SUM("sellers"."id") >= ? or "sellers"."id" < ?',
            $compiled->sql,
        );
        self::assertSame([10, 3], $compiled->bindings);
    }

    public function test_named_window_compiles_as_identifier_not_node_type(): void
    {
        $query = SelectQuery::make('treasury_movements')
            ->select(Aggregate::countAll()->over()->named('w')->as('rank_total'));

        self::assertSame(
            'select COUNT(*) over `w` as `rank_total` from `treasury_movements`',
            $this->compile($this->mariadb(), $query)->sql,
        );
        self::assertSame(
            'select COUNT(*) over "w" as "rank_total" from "treasury_movements"',
            $this->compile($this->sqlite(), $query)->sql,
        );
    }

    public function test_window_partition_order_and_frame(): void
    {
        $query = SelectQuery::make('treasury_movements')
            ->select('id')
            ->addSelect(
                Column::make('amount_mxn_real')->sum()
                    ->over()
                    ->partitionBy(Column::make('currency_id'))
                    ->orderBy(Column::make('id')->asc())
                    ->frame('rows between unbounded preceding and current row')
                    ->as('running'),
            );

        self::assertSame(
            'select "id", SUM("amount_mxn_real") over (partition by "currency_id" order by "id" asc rows between unbounded preceding and current row) as "running"'
            .' from "treasury_movements"',
            $this->compile($this->pgsql(), $query)->sql,
        );
    }

    public function test_recursive_cte_compiles_with_recursive_keyword(): void
    {
        $query = SelectQuery::make('tree')
            ->with(
                'tree',
                SelectQuery::make('nodes', 'n')
                    ->select('n.id', 'n.parent_id')
                    ->whereNull('n.parent_id')
                    ->unionAll(
                        SelectQuery::make('nodes', 'c')
                            ->select('c.id', 'c.parent_id')
                            ->join('tree', 'c.parent_id', '=', 'tree.id'),
                    ),
                true,
            )
            ->select('tree.id');

        self::assertSame(
            'with recursive "tree" as (select "n"."id", "n"."parent_id" from "nodes" as "n" where "n"."parent_id" is null'
            .' union all select "c"."id", "c"."parent_id" from "nodes" as "c" join "tree" on "c"."parent_id" = "tree"."id")'
            .' select "tree"."id" from "tree"',
            $this->compile($this->pgsql(), $query)->sql,
        );
    }

    public function test_raw_fragments_keep_bindings_in_every_clause(): void
    {
        $query = SelectQuery::make('treasury_movements')
            ->select(RawExpression::parameterized('COALESCE(NULLIF(class, ?), ?) as effective_class', ['', 'OTRO']))
            ->where(RawExpression::parameterized('COALESCE(schedule_date, pay_date) between ? and ?', ['2026-01-01', '2026-01-31']))
            ->groupBy(RawExpression::parameterized('COALESCE(NULLIF(class, ?), ?)', ['', 'OTRO']))
            ->having(RawExpression::parameterized('count(*) > ?', [2]));

        $compiled = $this->compile($this->sqlite(), $query);

        self::assertSame(
            'select COALESCE(NULLIF(class, ?), ?) as effective_class from "treasury_movements"'
            .' where COALESCE(schedule_date, pay_date) between ? and ?'
            .' group by COALESCE(NULLIF(class, ?), ?) having count(*) > ?',
            $compiled->sql,
        );
        self::assertSame(['', 'OTRO', '2026-01-01', '2026-01-31', '', 'OTRO', 2], $compiled->bindings);
    }
}
