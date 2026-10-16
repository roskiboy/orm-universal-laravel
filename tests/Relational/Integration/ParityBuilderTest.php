<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Real SQLite execution for the Laravel 13 parity API added in optimize-9.
 */
#[Group('integration')]
final class ParityBuilderTest extends TestCase
{
    private QueryExecutor $executor;

    protected function setUp(): void
    {
        $connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-parity');
        $this->executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

        $this->executor->execute(new RawQuery(
            'create table items ('
            .'id integer primary key autoincrement, '
            .'name text not null, '
            .'price integer not null default 0, '
            .'min_price integer not null default 0, '
            .'max_price integer not null default 0, '
            .'a integer not null default 0, '
            .'b integer not null default 0, '
            .'active integer not null default 1, '
            .'created_at text not null, '
            .'deleted_at text null, '
            .'options text null)',
        ));

        $this->items()->insertMany([
            ['name' => 'ana', 'price' => 10, 'min_price' => 5, 'max_price' => 15, 'a' => 1, 'b' => 2, 'active' => 1, 'created_at' => '2026-01-05 10:00:00', 'deleted_at' => null, 'options' => '{"color":"red","size":"M"}'],
            ['name' => 'bob', 'price' => 20, 'min_price' => 15, 'max_price' => 30, 'a' => 2, 'b' => 1, 'active' => 1, 'created_at' => '2026-01-06 18:30:00', 'deleted_at' => '2026-02-01 00:00:00', 'options' => '{"color":"blue"}'],
            ['name' => 'carla', 'price' => 30, 'min_price' => 25, 'max_price' => 35, 'a' => 3, 'b' => 3, 'active' => 0, 'created_at' => '2026-02-01 06:15:00', 'deleted_at' => null, 'options' => null],
            ['name' => 'dave', 'price' => 55, 'min_price' => 35, 'max_price' => 50, 'a' => 4, 'b' => 5, 'active' => 0, 'created_at' => '2026-03-01 23:59:59', 'deleted_at' => null, 'options' => '{"size":"L"}'],
        ]);
    }

    private function items(): QueryBuilder
    {
        return QueryBuilder::table($this->executor, 'items');
    }

    public function test_like_date_and_time_predicates(): void
    {
        self::assertSame(1, $this->items()->whereLike('name', 'a%')->count());
        self::assertSame(3, $this->items()->whereNotLike('name', 'a%')->count());
        self::assertSame(1, $this->items()->whereDate('created_at', '=', '2026-01-05')->count());
        self::assertSame(2, $this->items()->whereTime('created_at', '<', '12:00:00')->count());
        self::assertSame(4, $this->items()->whereYear('created_at', '2026')->count());
        self::assertSame(1, $this->items()->whereMonth('created_at', '3')->count());
    }

    public function test_between_columns_value_between_and_row_values(): void
    {
        self::assertSame(3, $this->items()->whereBetweenColumns('price', ['min_price', 'max_price'])->count());
        self::assertSame(1, $this->items()->whereValueBetween(22, ['min_price', 'max_price'])->count());
        self::assertSame(2, $this->items()->whereRowValues(['a', 'b'], '<', [3, 3])->count());
        self::assertSame(3, $this->items()->whereRowValues(['a', 'b'], '>=', [2, 1])->count());
    }

    public function test_all_any_none_groups(): void
    {
        self::assertSame(2, $this->items()->whereAll(['a', 'b'], '<', 3)->count());
        self::assertSame(1, $this->items()->whereAny(['a', 'b'], '=', 3)->count());
        self::assertSame(4, $this->items()->whereNone(['a', 'b'], '=', 0)->count());
    }

    public function test_null_safe_equals(): void
    {
        self::assertSame(3, $this->items()->whereNullSafeEquals('deleted_at', null)->count());
        self::assertSame(1, $this->items()->whereNullSafeEquals('deleted_at', '2026-02-01 00:00:00')->count());
    }

    public function test_json_key_predicates(): void
    {
        self::assertSame(2, $this->items()->whereJsonContainsKey('options', 'color')->count());
        self::assertSame(2, $this->items()->whereJsonDoesntContainKey('options', 'color')->count());
    }

    public function test_find_find_or_sole_and_first_or_fail(): void
    {
        $record = $this->items()->find(1);
        self::assertIsArray($record);
        self::assertSame('ana', $record['name']);

        $partial = $this->items()->find(1, ['name']);
        self::assertSame(['name' => 'ana'], $partial);

        self::assertNull($this->items()->find(999));
        self::assertSame('fallback', $this->items()->findOr(999, ['*'], fn (): string => 'fallback'));
        self::assertSame('ana', $this->items()->where('id', 1)->soleValue('name'));

        $this->expectException(QueryException::class);
        $this->items()->where('id', 999)->firstOrFail();
    }

    public function test_update_or_insert_insert_get_id_and_increment_each(): void
    {
        self::assertTrue($this->items()->updateOrInsert(['id' => 1], ['active' => 0]));
        self::assertSame(0, (int) $this->items()->find(1)['active']);

        self::assertFalse($this->items()->updateOrInsert(
            ['id' => 999],
            ['name' => 'eve', 'price' => 1, 'created_at' => '2026-04-01 00:00:00'],
        ));
        self::assertSame('eve', $this->items()->find(999)['name']);

        $id = $this->items()->insertGetId(['name' => 'frank', 'price' => 2, 'created_at' => '2026-04-02 00:00:00']);
        self::assertSame('frank', $this->items()->find($id)['name']);

        $affected = $this->items()->where('id', 1)->incrementEach(['a' => 5, 'b' => 7], ['active' => 1]);
        self::assertSame(2, $affected);
        $row = $this->items()->find(1);
        self::assertSame(6, (int) $row['a']);
        self::assertSame(9, (int) $row['b']);
        self::assertSame(1, (int) $row['active']);
    }

    public function test_insert_or_ignore_and_insert_using(): void
    {
        $ignored = $this->items()->insertOrIgnore([
            ['id' => 1, 'name' => 'dup', 'created_at' => '2026-01-01 00:00:00'],
        ]);

        self::assertSame(0, $ignored);
        self::assertSame('ana', $this->items()->find(1)['name']);

        $this->executor->execute(new RawQuery('create table archive (id integer primary key autoincrement, name text not null)'));

        $inserted = QueryBuilder::table($this->executor, 'archive')->insertUsing(
            ['name'],
            SelectQuery::make('items')->select('name')->where('active', 0),
        );

        self::assertSame(2, $inserted);
        self::assertSame(2, QueryBuilder::table($this->executor, 'archive')->count());
    }

    public function test_descending_chunks_each_and_chunk_map(): void
    {
        $ids = [];
        $this->items()->chunkByIdDesc(2, function (array $chunk) use (&$ids): void {
            foreach ($chunk as $row) {
                $ids[] = (int) $row['id'];
            }
        });

        self::assertSame([4, 3, 2, 1], $ids);

        $lazy = [];
        foreach ($this->items()->lazyByIdDesc(2) as $row) {
            $lazy[] = (int) $row['id'];
        }

        self::assertSame([4, 3, 2, 1], $lazy);

        $stopped = $this->items()->each(function (): bool {
            return false;
        });
        self::assertFalse($stopped);

        $mapped = $this->items()->orderBy('id')->chunkMap(fn (array $chunk): array => array_map(
            static fn (array $row): int => (int) $row['id'] * 10,
            $chunk,
        ), 2);

        self::assertSame([10, 20, 30, 40], $mapped);

        $ordered = [];
        $this->items()->orderedChunkById(2, function (array $chunk) use (&$ordered): void {
            foreach ($chunk as $row) {
                $ordered[] = (int) $row['id'];
            }
        }, 'id', true);

        self::assertSame([4, 3, 2, 1], $ordered);
    }

    public function test_conditionable_reorder_and_for_page(): void
    {
        $applied = null;
        $result = $this->items()
            ->when(true, fn (QueryBuilder $query): QueryBuilder => $query->where('active', 1))
            ->when(false, fn (QueryBuilder $query): QueryBuilder => $query->where('active', 0))
            ->unless(false, function (QueryBuilder $query) use (&$applied): void {
                $applied = true;
            })
            ->tap(fn (QueryBuilder $query): QueryBuilder => $query->orderBy('id'))
            ->pipe(fn (QueryBuilder $query): int => $query->count());

        self::assertTrue($applied);
        self::assertSame(2, $result);
        self::assertArrayHasKey('order by "id" asc', $this->items()->orderBy('id')->pipe(
            fn (QueryBuilder $query): array => ['order by "id" asc' => $query->sql()],
        ));

        self::assertStringContainsString('order by "name" desc', $this->items()->orderBy('id')->reorderDesc('name')->sql());
        self::assertStringNotContainsString('order by "id"', $this->items()->orderBy('id')->reorder('name')->sql());

        self::assertSame([3, 4], array_map('intval', $this->items()->orderBy('id')->forPage(2, 2)->pluck('id')));
        self::assertSame([3, 4], array_map('intval', $this->items()->forPageAfterId(2, 2)->pluck('id')));
    }

    public function test_or_variants_and_random_order(): void
    {
        self::assertSame(2, $this->items()->where('id', 1)->orWhereColumn('a', '=', 'b')->count());
        self::assertSame(4, $this->items()->where('id', 999)->orWhereRaw('1 = 1')->count());
        self::assertSame(3, $this->items()->where('id', 999)->orWhereNot('b', 2)->count());
        self::assertSame(4, $this->items()->inRandomOrder()->count());
        self::assertSame(2, $this->items()->where('id', 999)->orWhereIntegerInRaw('id', [3, 4])->count());
        self::assertSame(2, $this->items()->whereIntegerNotInRaw('id', [1, 2])->count());
    }

    public function test_cross_join_sub_and_truncate(): void
    {
        $active = $this->items()->select('id')->fromTable('items', 'inner_items')->where('inner_items.active', 1);

        self::assertSame(8, $this->items()->crossJoinSub($active, 'act')->count());

        $this->executor->execute(new RawQuery('create table tmp_trunc (id integer primary key autoincrement, name text)'));

        QueryBuilder::table($this->executor, 'tmp_trunc')->insertMany([
            ['name' => 'x'],
            ['name' => 'y'],
        ]);

        self::assertSame(2, QueryBuilder::table($this->executor, 'tmp_trunc')->count());

        QueryBuilder::table($this->executor, 'tmp_trunc')->truncate();

        self::assertSame(0, QueryBuilder::table($this->executor, 'tmp_trunc')->count());
    }

    public function test_in_order_of_from_raw_and_group_limit(): void
    {
        self::assertSame([2, 1, 3, 4], array_map('intval', $this->items()->inOrderOf('a', [2, 1, 3])->pluck('id')));

        self::assertSame(['ana'], array_values($this->items()->fromRaw('items as i')->select('i.name')->where('i.id', 1)->pluck('name')));

        $topPerGroup = array_map('intval', $this->items()
            ->orderByDesc('id')
            ->groupLimit(1, 'active')
            ->pluck('id'));

        sort($topPerGroup);

        self::assertSame([2, 4], $topPerGroup);
    }

    public function test_json_length_execution_and_overlaps_exception(): void
    {
        $this->executor->execute(new RawQuery('create table json_items (id integer primary key autoincrement, options text)'));

        QueryBuilder::table($this->executor, 'json_items')->insertMany([
            ['options' => '["a"]'],
            ['options' => '["a","b"]'],
            ['options' => '["a","b","c"]'],
        ]);

        self::assertSame(2, QueryBuilder::table($this->executor, 'json_items')->whereJsonLength('options', '>=', 2)->count());
        self::assertSame(1, QueryBuilder::table($this->executor, 'json_items')->whereJsonLength('options', 1)->count());

        self::assertSame(3, QueryBuilder::table($this->executor, 'json_items')->whereJsonOverlaps('options', ['a'])->count());
        self::assertSame(1, QueryBuilder::table($this->executor, 'json_items')->whereJsonOverlaps('options', ['c'])->count());
        self::assertSame(3, QueryBuilder::table($this->executor, 'json_items')->whereJsonDoesntOverlap('options', ['z'])->count());
    }

    public function test_macros_extend_the_builder_deterministically(): void
    {
        QueryBuilder::macro('onlyActive', fn (): QueryBuilder => $this->where('active', true));

        self::assertSame(2, $this->items()->onlyActive()->count());

        QueryBuilder::flushMacros();

        $this->expectException(\BadMethodCallException::class);
        $this->items()->onlyActive();
    }

    public function test_magic_json_paths_execute_on_sqlite(): void
    {
        self::assertSame(1, $this->items()->where('options->color', '=', 'red')->count());
        self::assertSame(1, $this->items()->where('options->size', 'like', 'M')->count());
        self::assertSame(3, $this->items()->whereNotNull('options')->count());

        $row = (array) $this->items()->select('name', 'options->color')->where('options->color', '=', 'red')->first();
        self::assertSame('ana', $row['name']);
        self::assertSame('red', array_values($row)[1] ?? null);
    }

    public function test_bulk_create_update_or_create_and_insert_or_ignore_many(): void
    {
        self::assertSame(2, $this->items()->createMany([
            ['name' => 'erin', 'price' => 60, 'min_price' => 0, 'max_price' => 0, 'a' => 0, 'b' => 0, 'active' => 1, 'created_at' => '2026-04-01 00:00:00', 'deleted_at' => null, 'options' => null],
            ['name' => 'frank', 'price' => 70, 'min_price' => 0, 'max_price' => 0, 'a' => 0, 'b' => 0, 'active' => 1, 'created_at' => '2026-04-02 00:00:00', 'deleted_at' => null, 'options' => null],
        ]));
        self::assertSame(6, $this->items()->count());

        $this->executor->execute(new RawQuery(
            'create table unique_items (id integer primary key autoincrement, name text unique, price integer not null)',
        ));
        $unique = fn (): QueryBuilder => QueryBuilder::table($this->executor, 'unique_items');

        self::assertSame(2, $unique()->insertMany([
            ['name' => 'ana', 'price' => 10],
            ['name' => 'bob', 'price' => 20],
        ]));

        self::assertSame(2, $unique()->updateOrCreateMany([
            ['name' => 'ana', 'price' => 111],
            ['name' => 'zoe', 'price' => 80],
        ], ['name'], ['price']));

        self::assertSame(111, (int) $unique()->where('name', 'ana')->value('price'));
        self::assertSame(3, $unique()->count());

        self::assertSame(1, $unique()->insertOrIgnoreMany([
            ['name' => 'ana', 'price' => 0],
            ['name' => 'newcomer', 'price' => 90],
        ]));
        self::assertSame(4, $unique()->count());
        self::assertSame(111, (int) $unique()->where('name', 'ana')->value('price'));
    }

    public function test_insert_or_ignore_returning_executes_on_sqlite(): void
    {
        $this->executor->execute(new RawQuery('create table ign_items (id integer primary key, name text unique)'));

        self::assertSame(
            [['id' => 1, 'name' => 'ana']],
            QueryBuilder::table($this->executor, 'ign_items')->insertOrIgnoreReturning(
                [['id' => 1, 'name' => 'ana']],
                ['id', 'name'],
                'name',
            ),
        );

        $inserted = QueryBuilder::table($this->executor, 'ign_items')->insertOrIgnoreReturning(
            [['id' => 1, 'name' => 'ana'], ['id' => 2, 'name' => 'bob']],
            ['id', 'name'],
            'name',
        );

        self::assertSame([['id' => 2, 'name' => 'bob']], $inserted);
        self::assertSame(2, QueryBuilder::table($this->executor, 'ign_items')->count());
    }
}
