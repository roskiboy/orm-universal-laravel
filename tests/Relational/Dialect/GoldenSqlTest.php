<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Dialect;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\DeleteQuery;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Dialect\MariaDbDialect;
use OrmUniversal\Relational\Dialect\MySqlDialect;
use OrmUniversal\Relational\Dialect\OracleDialect;
use OrmUniversal\Relational\Dialect\PostgreSqlDialect;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Dialect\SqlServerDialect;
use PHPUnit\Framework\TestCase;

final class GoldenSqlTest extends TestCase
{
    private function mysql(): AbstractDialect
    {
        return MySqlDialect::make('8.4.3');
    }

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

    private function sqlserver(): AbstractDialect
    {
        return SqlServerDialect::make('2022');
    }

    private function oracle(): AbstractDialect
    {
        return OracleDialect::make('19.0');
    }

    private function compile(AbstractDialect $dialect, SelectQuery|InsertQuery|DeleteQuery $query): CompiledQuery
    {
        return $dialect->compile($query);
    }

    public function test_simple_select_quotes_every_dialect(): void
    {
        $query = SelectQuery::make('users')
            ->select('id', 'name')
            ->where('active', true);

        self::assertSame(
            'select `id`, `name` from `users` where `active` = ?',
            $this->compile($this->mysql(), $query)->sql,
        );
        self::assertSame(
            'select "id", "name" from "users" where "active" = ?',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'select [id], [name] from [users] where [active] = ?',
            $this->compile($this->sqlserver(), $query)->sql,
        );
        self::assertSame([true], $this->compile($this->mysql(), $query)->bindings);
    }

    public function test_object_oriented_expressions_and_nested_closures(): void
    {
        $query = SelectQuery::make('users')
            ->select(Column::make('id'), Column::make('age')->as('years'))
            ->where(function ($group): void {
                $group->where('active', true)->orWhere(Column::make('age')->gte(18));
            })
            ->whereNotNull('email');

        $compiled = $this->compile($this->pgsql(), $query);

        self::assertSame(
            'select "id", "age" as "years" from "users" where ("active" = ? or "age" >= ?) and "email" is not null',
            $compiled->sql,
        );
        self::assertSame([true, 18], $compiled->bindings);
    }

    public function test_order_limit_offset_per_dialect(): void
    {
        $query = SelectQuery::make('users')
            ->select('id')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->offset(5);

        self::assertSame(
            'select `id` from `users` order by `id` desc limit 10 offset 5',
            $this->compile($this->mysql(), $query)->sql,
        );
        self::assertSame(
            'select [id] from [users] order by [id] desc offset 5 rows fetch next 10 rows only',
            $this->compile($this->sqlserver(), $query)->sql,
        );
        self::assertSame(
            'select "id" from "users" order by "id" desc offset 5 rows fetch next 10 rows only',
            $this->compile($this->oracle(), $query)->sql,
        );
    }

    public function test_sqlserver_injects_order_by_for_bare_offset(): void
    {
        $query = SelectQuery::make('users')->select('id')->offset(20)->limit(10);

        self::assertSame(
            'select [id] from [users] order by (select null) offset 20 rows fetch next 10 rows only',
            $this->compile($this->sqlserver(), $query)->sql,
        );
    }

    public function test_join_alias_from_table_identifier_is_preserved(): void
    {
        $query = SelectQuery::make('users')
            ->select('u.id')
            ->join(TableIdentifier::fromString('orders', 'o'), 'o.user_id', '=', 'u.id');

        self::assertSame(
            'select "u"."id" from "users" join "orders" as "o" on "o"."user_id" = "u"."id"',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'select `u`.`id` from `users` join `orders` as `o` on `o`.`user_id` = `u`.`id`',
            $this->compile($this->mysql(), $query)->sql,
        );
    }

    public function test_offset_without_limit_per_dialect(): void
    {
        $query = SelectQuery::make('users')->select('id')->orderBy('id')->offset(5);

        self::assertSame(
            'select `id` from `users` order by `id` asc limit 18446744073709551615 offset 5',
            $this->compile($this->mysql(), $query)->sql,
        );
        self::assertSame(
            'select `id` from `users` order by `id` asc limit 18446744073709551615 offset 5',
            $this->compile($this->mariadb(), $query)->sql,
        );
        self::assertSame(
            'select "id" from "users" order by "id" asc limit all offset 5',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'select "id" from "users" order by "id" asc limit -1 offset 5',
            $this->compile($this->sqlite(), $query)->sql,
        );
    }

    public function test_exists_projection_per_dialect(): void
    {
        $query = SelectQuery::make('users')->where('active', true);

        self::assertSame(
            'select exists(select * from `users` where `active` = ?) as `exists`',
            $this->mysql()->compileExists($query)->sql,
        );
        self::assertSame(
            'select exists(select * from `users` where `active` = ?) as `exists`',
            $this->mariadb()->compileExists($query)->sql,
        );
        self::assertSame(
            'select exists(select * from "users" where "active" = ?) as "exists"',
            $this->pgsql()->compileExists($query)->sql,
        );
        self::assertSame(
            'select exists(select * from "users" where "active" = ?) as "exists"',
            $this->sqlite()->compileExists($query)->sql,
        );
        self::assertSame(
            'select case when exists(select * from [users] where [active] = ?) then 1 else 0 end as [exists]',
            $this->sqlserver()->compileExists($query)->sql,
        );
        self::assertSame(
            'select case when exists(select * from "users" where "active" = ?) then 1 else 0 end as "exists"',
            $this->oracle()->compileExists($query)->sql,
        );
    }

    public function test_exists_projection_capability_gates_the_fast_path(): void
    {
        self::assertTrue($this->mysql()->supports(Capability::EXISTS_PROJECTION));
        self::assertTrue($this->mariadb()->supports(Capability::EXISTS_PROJECTION));
        self::assertTrue($this->pgsql()->supports(Capability::EXISTS_PROJECTION));
        self::assertTrue($this->sqlite()->supports(Capability::EXISTS_PROJECTION));
        self::assertFalse($this->sqlserver()->supports(Capability::EXISTS_PROJECTION));
        self::assertFalse($this->oracle()->supports(Capability::EXISTS_PROJECTION));
    }

    public function test_insert_returning_is_dialect_negotiated(): void
    {
        $query = InsertQuery::into('users')->values(['name' => 'ana'])->returning(['id', 'name']);

        self::assertSame(
            'insert into `users` (`name`) values (?) returning `id`, `name`',
            $this->compile($this->mariadb(), $query)->sql,
        );
        self::assertSame(
            'insert into "users" ("name") values (?) returning "id", "name"',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'insert into "users" ("name") values (?) returning "id", "name"',
            $this->compile($this->sqlite(), $query)->sql,
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->compile($this->mysql(), $query);
    }

    public function test_insert_output_clause_for_sqlserver(): void
    {
        $query = InsertQuery::into('users')->values(['name' => 'ana'])->returning(['id']);

        self::assertSame(
            'insert into [users] ([name]) output inserted.[id] values (?)',
            $this->compile($this->sqlserver(), $query)->sql,
        );
    }

    public function test_upsert_per_dialect(): void
    {
        $query = InsertQuery::into('users')
            ->values(['id' => 1, 'name' => 'ana'])
            ->onConflict(['id'])
            ->upsert(['name']);

        self::assertSame(
            'insert into `users` (`id`, `name`) values (?, ?) on duplicate key update `name` = VALUES(`name`)',
            $this->compile($this->mariadb(), $query)->sql,
        );
        self::assertSame(
            'insert into "users" ("id", "name") values (?, ?) on conflict ("id") do update set "name" = excluded."name"',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'merge into [users] target using (values (?, ?)) src ([id], [name]) on (target.[id] = src.[id]) when matched then update set target.[name] = src.[name] when not matched then insert ([id], [name]) values (src.[id], src.[name]);',
            $this->compile($this->sqlserver(), $query)->sql,
        );
    }

    public function test_json_access_per_dialect(): void
    {
        $query = SelectQuery::make('events')
            ->select('id')
            ->where(Expression::json(Column::make('meta'), '$.type', true), '=', 'login');

        self::assertSame(
            'select `id` from `events` where JSON_UNQUOTE(JSON_EXTRACT(`meta`, \'$.type\')) = ?',
            $this->compile($this->mysql(), $query)->sql,
        );
        self::assertSame(
            'select "id" from "events" where ("meta" #>> \'{type}\') = ?',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'select "id" from "events" where json_extract("meta", \'$.type\') = ?',
            $this->compile($this->sqlite(), $query)->sql,
        );
    }

    public function test_cte_exists_between_and_in(): void
    {
        $cte = SelectQuery::make('users')->select('id')->where('active', true);

        $query = SelectQuery::make('orders')
            ->select('id')
            ->with('active_users', $cte)
            ->where(Column::make('total')->between(10, 100))
            ->whereIn('user_id', [1, 2, 3])
            ->whereExists(SelectQuery::make('payments')->select('id')->whereColumn('payments.order_id', '=', 'orders.id'));

        $compiled = $this->compile($this->pgsql(), $query);

        self::assertSame(
            'with "active_users" as (select "id" from "users" where "active" = ?) select "id" from "orders" where "total" between ? and ? and "user_id" in (?, ?, ?) and exists (select "id" from "payments" where "payments"."order_id" = "orders"."id")',
            $compiled->sql,
        );
        self::assertSame([true, 10, 100, 1, 2, 3], $compiled->bindings);
    }

    public function test_delete_limit_is_negotiated(): void
    {
        $query = DeleteQuery::from('sessions')->where('expired', true)->limit(100);

        self::assertSame(
            'delete from `sessions` where `expired` = ? limit 100',
            $this->compile($this->mysql(), $query)->sql,
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->compile($this->pgsql(), $query);
    }

    public function test_window_functions_are_negotiated(): void
    {
        $query = SelectQuery::make('sales')
            ->select(Column::make('amount')->sum()->over()->partitionBy(Column::make('region'))->as('region_total'));

        $compiled = $this->compile($this->pgsql(), $query);

        self::assertSame(
            'select SUM("amount") over (partition by "region") as "region_total" from "sales"',
            $compiled->sql,
        );
    }

    public function test_lock_clause_and_capability(): void
    {
        $query = SelectQuery::make('jobs')->select('id')->lock('for update skip locked');

        self::assertSame(
            'select "id" from "jobs" for update skip locked',
            $this->compile($this->pgsql(), $query)->sql,
        );
        self::assertSame(
            'select "id" from "jobs" for update skip locked',
            $this->compile($this->oracle(), $query)->sql,
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->compile($this->sqlserver(), $query);
    }

    public function test_union_with_outer_order_and_limit(): void
    {
        $left = SelectQuery::make('active_users')->select('id');
        $right = SelectQuery::make('archived_users')->select('id');

        $query = $left->unionAll($right)->orderBy('id')->limit(5);

        self::assertSame(
            'select "id" from "active_users" union all select "id" from "archived_users" order by "id" asc limit 5',
            $this->compile($this->pgsql(), $query)->sql,
        );
    }
}
