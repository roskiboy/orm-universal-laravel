<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Dialect;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\UpdateQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Dialect\MariaDbDialect;
use OrmUniversal\Relational\Dialect\MySqlDialect;
use OrmUniversal\Relational\Dialect\OracleDialect;
use OrmUniversal\Relational\Dialect\PostgreSqlDialect;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Dialect\SqlServerDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Golden SQL for the Laravel 13 parity API added in optimize-9: date/time
 * predicates, LIKE variants, null-safe and binary comparisons, row values,
 * column-between, all/any/none groups, JSON key predicates and INSERT IGNORE.
 */
final class ParityGoldenTest extends TestCase
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

    private function builder(AbstractDialect $dialect): QueryBuilder
    {
        $connection = new PdoConnection(new PDO('sqlite::memory:'), 'parity-golden');

        return QueryBuilder::table(new QueryExecutor($connection, $dialect), 'users');
    }

    public function test_like_and_binary_like(): void
    {
        self::assertSame(
            'select * from `users` where `name` like ?',
            $this->builder($this->mariadb())->whereLike('name', 'a%')->sql(),
        );
        self::assertSame(
            'select * from `users` where `name` like binary ?',
            $this->builder($this->mariadb())->whereLike('name', 'a%', true)->sql(),
        );
        self::assertSame(
            'select * from `users` where `name` not like ?',
            $this->builder($this->mariadb())->whereNotLike('name', 'a%')->sql(),
        );
        self::assertSame(
            'select * from "users" where "name" like ?',
            $this->builder($this->pgsql())->whereLike('name', 'a%')->sql(),
        );
    }

    public function test_binary_comparison_is_mysql_only(): void
    {
        self::assertSame(
            'select * from `users` where `name` = binary ?',
            $this->builder($this->mariadb())->whereBinary('name', 'ana')->sql(),
        );
        self::assertSame(
            'select * from `users` where `name` != binary ?',
            $this->builder($this->mariadb())->whereNotBinary('name', 'ana')->sql(),
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->builder($this->pgsql())->whereBinary('name', 'ana')->sql();
    }

    public function test_case_sensitive_like_is_mysql_only(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        $this->builder($this->sqlite())->whereLike('name', 'a%', true)->sql();
    }

    public function test_null_safe_equals(): void
    {
        self::assertSame(
            'select * from `users` where (`deleted_at` <=> ?)',
            $this->builder($this->mariadb())->whereNullSafeEquals('deleted_at', null)->sql(),
        );
        self::assertSame(
            'select * from "users" where "deleted_at" is not distinct from ?',
            $this->builder($this->pgsql())->whereNullSafeEquals('deleted_at', null)->sql(),
        );
        self::assertSame(
            'select * from "users" where "deleted_at" is ?',
            $this->builder($this->sqlite())->whereNullSafeEquals('deleted_at', null)->sql(),
        );
    }

    public function test_date_and_time_predicates(): void
    {
        self::assertSame(
            'select * from `users` where date(`created_at`) = ?',
            $this->builder($this->mariadb())->whereDate('created_at', '=', '2026-10-12')->sql(),
        );
        self::assertSame(
            'select * from `users` where time(`created_at`) < ?',
            $this->builder($this->mariadb())->whereTime('created_at', '<', '12:00:00')->sql(),
        );
        self::assertSame(
            'select * from "users" where ("created_at")::date = ?',
            $this->builder($this->pgsql())->whereDate('created_at', '=', '2026-10-12')->sql(),
        );
        self::assertSame(
            "select * from \"users\" where strftime('%Y-%m-%d', \"created_at\") = ?",
            $this->builder($this->sqlite())->whereDate('created_at', '=', '2026-10-12')->sql(),
        );
    }

    public function test_relative_date_sugar(): void
    {
        $today = new \DateTimeImmutable('2026-10-12 15:30:00');

        self::assertSame(
            'select * from `users` where date(`created_at`) >= ?',
            $this->builder($this->mariadb())->whereTodayOrAfter('created_at', $today)->sql(),
        );
        self::assertSame(
            'select * from `users` where date(`created_at`) < ?',
            $this->builder($this->mariadb())->whereBeforeToday('created_at', $today)->sql(),
        );
        self::assertSame(
            ['2026-10-12'],
            $this->builder($this->mariadb())->whereToday('created_at', $today)->bindings(),
        );
    }

    public function test_between_columns_and_value_between(): void
    {
        self::assertSame(
            'select * from `users` where `age` between `min_age` and `max_age`',
            $this->builder($this->mariadb())->whereBetweenColumns('age', ['min_age', 'max_age'])->sql(),
        );
        self::assertSame(
            'select * from `users` where ? between `min_age` and `max_age`',
            $this->builder($this->mariadb())->whereValueBetween(18, ['min_age', 'max_age'])->sql(),
        );
        self::assertSame(
            'select * from `users` where `age` not between `min_age` and `max_age`',
            $this->builder($this->mariadb())->whereNotBetweenColumns('age', ['min_age', 'max_age'])->sql(),
        );
    }

    public function test_row_values(): void
    {
        self::assertSame(
            'select * from `users` where (`tenant_id`, `id`) > (?, ?)',
            $this->builder($this->mariadb())->whereRowValues(['tenant_id', 'id'], '>', [1, 10])->sql(),
        );
        self::assertSame(
            'select * from "users" where ("tenant_id", "id") = (?, ?)',
            $this->builder($this->pgsql())->whereRowValues(['tenant_id', 'id'], '=', [1, 10])->sql(),
        );
    }

    public function test_all_any_none_groups(): void
    {
        self::assertSame(
            'select * from `users` where `active` = ? and (`a` > ? or `b` > ?)',
            $this->builder($this->mariadb())->where('active', true)->whereAny(['a', 'b'], '>', 0)->sql(),
        );
        self::assertSame(
            'select * from `users` where `a` > ? and `b` > ?',
            $this->builder($this->mariadb())->whereAll(['a', 'b'], '>', 0)->sql(),
        );
        self::assertSame(
            'select * from `users` where `a` <> ? and `b` <> ?',
            $this->builder($this->mariadb())->whereNone(['a', 'b'], '=', 9)->sql(),
        );
    }

    public function test_json_key_predicates(): void
    {
        self::assertSame(
            "select * from `users` where JSON_CONTAINS_PATH(`options`, 'one', '$.color')",
            $this->builder($this->mariadb())->whereJsonContainsKey('options', 'color')->sql(),
        );
        self::assertSame(
            "select * from `users` where not (JSON_CONTAINS(`options`, ?, '$'))",
            $this->builder($this->mariadb())->whereJsonDoesntContain('options', 'red')->sql(),
        );
        self::assertSame(
            'select * from "users" where not ("options" #> \'{color}\' is not null)',
            $this->builder($this->pgsql())->whereJsonDoesntContainKey('options', 'color')->sql(),
        );
    }

    public function test_random_order_and_cross_join_sub(): void
    {
        self::assertSame(
            'select * from `users` order by rand() asc',
            $this->builder($this->mariadb())->inRandomOrder()->sql(),
        );
        self::assertSame(
            'select * from [users] order by newid() asc',
            $this->builder(SqlServerDialect::make('2022'))->inRandomOrder()->sql(),
        );

        $sub = $this->builder($this->mariadb())->select('val')->fromTable('settings');

        self::assertSame(
            'select `users`.`id`, `ext`.`val` from `users` cross join (select `val` from `settings`) as `ext`',
            $this->builder($this->mariadb())->select('users.id', 'ext.val')->crossJoinSub($sub, 'ext')->sql(),
        );
    }

    public function test_join_where_adds_on_condition(): void
    {
        self::assertSame(
            'select * from `users` join `roles` on `roles`.`user_id` = ? where `roles`.`active` = ?',
            $this->builder($this->mariadb())
                ->joinWhere('roles', 'roles.user_id', '=', 1)
                ->where('roles.active', true)
                ->sql(),
        );
    }

    public function test_insert_ignore_and_insert_using(): void
    {
        $insert = InsertQuery::into('users')->intoColumns(['name'])->values(['name' => 'ana'])->ignore();

        self::assertSame(
            'insert ignore into `users` (`name`) values (?)',
            $this->mariadb()->compile($insert)->sql,
        );
        self::assertSame(
            'insert or ignore into "users" ("name") values (?)',
            $this->sqlite()->compile($insert)->sql,
        );
        self::assertSame(
            'insert into "users" ("name") values (?) on conflict do nothing',
            $this->pgsql()->compile($insert)->sql,
        );

        $select = SelectQuery::make('old_users')->select('name');
        $using = InsertQuery::into('users')->intoColumns(['name'])->fromSelect($select);

        self::assertSame(
            'insert into `users` (`name`) select `name` from `old_users`',
            $this->mariadb()->compile($using)->sql,
        );
    }

    public function test_null_safe_or_variants_and_reorder(): void
    {
        self::assertSame(
            'select * from `users` where `active` = ? or `a` is null',
            $this->builder($this->mariadb())->where('active', true)->orWhereNull('a')->sql(),
        );
        self::assertSame(
            'select * from `users` order by `name` desc',
            $this->builder($this->mariadb())->orderBy('id')->reorderDesc('name')->sql(),
        );
        self::assertSame(
            'select * from `users`',
            $this->builder($this->mariadb())->orderBy('id')->reorder()->sql(),
        );
    }

    public function test_in_order_of_and_from_raw(): void
    {
        self::assertSame(
            'select * from `users` order by case when `status` = ? then ? when `status` = ? then ? else ? end asc',
            $this->builder($this->mariadb())->inOrderOf('status', ['activo', 'pausado'])->sql(),
        );
        self::assertSame(
            'select * from items as i where `i`.`active` = ?',
            $this->builder($this->mariadb())->fromRaw('items as i')->where('i.active', true)->sql(),
        );
    }

    public function test_group_limit_wraps_with_row_number(): void
    {
        self::assertSame(
            'select * from (select *, ROW_NUMBER() over (partition by `tenant_id` order by `id` desc) as `laravel_row` from `users`) as `laravel_table` where `laravel_row` <= ? order by `laravel_row` asc',
            $this->builder($this->mariadb())->orderByDesc('id')->groupLimit(3, 'tenant_id')->sql(),
        );
    }

    public function test_index_hints_are_mysql_only(): void
    {
        self::assertSame(
            'select * from `users` force index (`idx_active`)',
            $this->builder($this->mariadb())->forceIndex('idx_active')->sql(),
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->builder($this->pgsql())->useIndex('idx_active')->sql();
    }

    public function test_json_length_and_overlaps(): void
    {
        self::assertSame(
            "select * from `users` where JSON_LENGTH(`options`, '$') > ?",
            $this->builder($this->mariadb())->whereJsonLength('options', '>', 1)->sql(),
        );
        self::assertSame(
            'select * from "users" where jsonb_array_length(("options" #> \'{}\')::jsonb) > ?',
            $this->builder($this->pgsql())->whereJsonLength('options', '>', 1)->sql(),
        );
        self::assertSame(
            "select * from \"users\" where json_array_length(\"options\", '$') > ?",
            $this->builder($this->sqlite())->whereJsonLength('options', '>', 1)->sql(),
        );
        self::assertSame(
            'select * from `users` where JSON_OVERLAPS(`options`, ?)',
            $this->builder($this->mariadb())->whereJsonOverlaps('options', ['a', 'b'])->sql(),
        );
        self::assertSame(
            'select * from `users` where not (JSON_OVERLAPS(`options`, ?))',
            $this->builder($this->mariadb())->whereJsonDoesntOverlap('options', ['a', 'b'])->sql(),
        );
        self::assertSame(
            'select * from "users" where exists (select 1 from jsonb_array_elements(("options")::jsonb) as elem'
            .' where elem in (select e from jsonb_array_elements((?)::jsonb) as e))',
            $this->builder($this->pgsql())->whereJsonOverlaps('options', ['a'])->sql(),
        );
        self::assertSame(
            'select * from "users" where exists (select 1 from json_each("options") where json_each.value in'
            .' (select value from json_each(?)))',
            $this->builder($this->sqlite())->whereJsonOverlaps('options', ['a'])->sql(),
        );
    }

    public function test_first_or_fail_and_macros(): void
    {
        QueryBuilder::macro('onlyActive', fn (): QueryBuilder => $this->where('active', true));

        self::assertTrue(QueryBuilder::hasMacro('onlyActive'));

        $builder = $this->builder($this->mariadb())->onlyActive();

        self::assertSame('select * from `users` where `active` = ?', $builder->sql());

        QueryBuilder::flushMacros();

        self::assertFalse(QueryBuilder::hasMacro('onlyActive'));
    }

    public function test_fulltext_predicates_per_dialect(): void
    {
        self::assertSame(
            'select * from `users` where match (`name`, `email`) against (? in boolean mode)',
            $this->builder($this->mariadb())->whereFullText(['name', 'email'], 'foo', ['mode' => 'boolean'])->sql(),
        );
        self::assertSame(
            'select * from `users` where match (`name`) against (? in natural language mode with query expansion)',
            $this->builder($this->mariadb())->whereFullText('name', 'foo', ['expanded' => true])->sql(),
        );
        self::assertSame(
            "select * from \"users\" where (to_tsvector('spanish', \"name\")) @@ websearch_to_tsquery('spanish', ?)",
            $this->builder($this->pgsql())->whereFullText('name', 'foo', ['language' => 'spanish', 'mode' => 'websearch'])->sql(),
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->builder($this->sqlite())->whereFullText('name', 'foo')->sql();
    }

    public function test_lateral_joins_per_dialect(): void
    {
        $pgsql = $this->builder($this->pgsql());
        $sub = $pgsql->select('val')->fromTable('settings');

        self::assertSame(
            'select * from "users" join lateral (select "val" from "settings") as "s" on true',
            $this->builder($this->pgsql())->joinLateral($sub, 's')->sql(),
        );
        self::assertSame(
            'select * from "users" left join lateral (select "val" from "settings") as "s" on true',
            $this->builder($this->pgsql())->leftJoinLateral($sub, 's')->sql(),
        );
        self::assertSame(
            'select * from [users] cross apply (select [val] from [settings]) as [s]',
            $this->builder(SqlServerDialect::make('2022'))->joinLateral($sub, 's')->sql(),
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->builder($this->mariadb())->joinLateral($sub, 's')->sql();
    }

    public function test_update_from_is_translated_per_dialect(): void
    {
        $update = UpdateQuery::make('users')
            ->set(['status' => 'x'])
            ->from('other')
            ->where(Comparison::of(Column::make('id'), '=', Expression::normalizeOperand(1)));

        self::assertSame(
            'update "users" set "status" = ? from "other" where "id" = ?',
            $this->pgsql()->compile($update)->sql,
        );
        self::assertSame(
            'update `users`, `other` set `status` = ? where `id` = ?',
            $this->mariadb()->compile($update)->sql,
        );
        self::assertSame(
            'update "users" set "status" = ? from "other" where "id" = ?',
            $this->sqlite()->compile($update)->sql,
        );

        $this->expectException(UnsupportedFeatureException::class);
        OracleDialect::make('23.0')->compile($update)->sql;
    }

    public function test_vector_distance_per_capability(): void
    {
        self::assertSame(
            'select * from `users` where vec_distance_cosine(`embedding`, vec_fromtext(?)) < ? order by vec_distance_cosine(`embedding`, vec_fromtext(?)) asc',
            $this->builder(MariaDbDialect::make('11.8.0'))
                ->whereVectorSimilarTo('embedding', [0.1, 0.2], 0.6)
                ->sql(),
        );
        self::assertSame(
            'select vec_distance_cosine(`embedding`, vec_fromtext(?)) as `embedding_distance` from `users`',
            $this->builder(MariaDbDialect::make('11.8.0'))
                ->selectVectorDistance('embedding', '[0.1,0.2]')
                ->sql(),
        );
        self::assertSame(
            'select * from "users" where ("embedding" <=> ?) < ?',
            $this->builder($this->pgsql())->whereVectorDistanceLessThan('embedding', [0.1, 0.2], 0.3)->sql(),
        );

        $this->expectException(UnsupportedFeatureException::class);
        $this->builder($this->mariadb())->whereVectorDistanceLessThan('embedding', [0.1], 0.3)->sql();
    }

    public function test_straight_join_and_timeout_translation(): void
    {
        self::assertSame(
            'select * from `users` straight join `roles` on `roles`.`user_id` = `users`.`id`',
            $this->builder($this->mariadb())->straightJoin('roles', 'roles.user_id', '=', 'users.id')->sql(),
        );

        $degraded = $this->builder($this->pgsql())->straightJoin('roles', 'roles.user_id', '=', 'users.id');
        self::assertSame(
            'select * from "users" join "roles" on "roles"."user_id" = "users"."id"',
            $degraded->sql(),
        );
        self::assertNotEmpty($degraded->compile()->warnings);

        $timed = $this->builder($this->mariadb())->timeout(30);
        self::assertSame('select * from `users`', $timed->sql());
        self::assertSame([], $timed->compile()->warnings);

        $unsupported = $this->builder($this->sqlite())->timeout(30);
        self::assertNotEmpty($unsupported->compile()->warnings);
    }

    public function test_magic_json_paths_in_plain_strings(): void
    {
        $query = SelectQuery::make('events')
            ->select('data->prefs->theme')
            ->where('data->prefs->theme', '=', 'dark')
            ->groupBy('data->kind')
            ->orderBy('data->rank', 'desc');

        self::assertSame(
            "select JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.prefs.theme')) from `events`"
            ." where JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.prefs.theme')) = ?"
            ." group by JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.kind'))"
            ." order by JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.rank')) desc",
            $this->mariadb()->compile($query)->sql,
        );
        self::assertSame(
            'select ("data" #>> \'{prefs,theme}\') from "events" where ("data" #>> \'{prefs,theme}\') = ?'
            .' group by ("data" #>> \'{kind}\') order by ("data" #>> \'{rank}\') desc',
            $this->pgsql()->compile($query)->sql,
        );
        self::assertSame(
            'select json_extract("data", \'$.prefs.theme\') from "events" where json_extract("data", \'$.prefs.theme\') = ?'
            .' group by json_extract("data", \'$.kind\') order by json_extract("data", \'$.rank\') desc',
            $this->sqlite()->compile($query)->sql,
        );

        $like = SelectQuery::make('events')->where('data->channel', 'like', 'web%');
        self::assertSame(
            "select * from `events` where JSON_UNQUOTE(JSON_EXTRACT(`data`, '$.channel')) like ?",
            $this->mariadb()->compile($like)->sql,
        );
    }

    public function test_udf_calls_compile_per_dialect(): void
    {
        $query = SelectQuery::make('users')->select(Expression::func('coalesce', Column::make('nickname'), Column::make('name')));
        self::assertSame('select COALESCE(`nickname`, `name`) from `users`', $this->mariadb()->compile($query)->sql);
        self::assertSame('select COALESCE("nickname", "name") from "users"', $this->pgsql()->compile($query)->sql);
    }

    public function test_insert_or_ignore_returning_requires_capability_or_unique_by(): void
    {
        $this->expectException(UnsupportedFeatureException::class);

        $this->builder(MySqlDialect::make('8.4.0'))
            ->insertOrIgnoreReturning([['name' => 'ana']], ['id', 'name']);
    }
}
