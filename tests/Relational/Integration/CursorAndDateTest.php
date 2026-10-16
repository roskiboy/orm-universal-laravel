<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/* */
#[Group('integration')]
final class CursorAndDateTest extends TestCase
{
    private QueryExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'cursor-test'),
            SqliteDialect::make('3.49.2'),
        );

        $this->executor->execute(new RawQuery('create table events (id integer primary key, happened_at text not null, payload text not null)'));
        $this->executor->execute(new RawQuery(
            'insert into events (id, happened_at, payload) values '.
            "(1, '2026-01-15 10:00:00', '{\"type\":\"login\"}'),".
            "(2, '2026-02-20 11:00:00', '{\"type\":\"logout\"}'),".
            "(3, '2026-02-25 12:00:00', '{\"type\":\"login\"}'),".
            "(4, '2025-12-31 23:00:00', '{\"type\":\"login\"}')",
        ));
    }

    public function test_where_year_and_where_day(): void
    {
        $count = QueryBuilder::table($this->executor, 'events')->whereYear('happened_at', 2026)->count();
        self::assertSame(3, $count);

        $day = QueryBuilder::table($this->executor, 'events')->whereDay('happened_at', 15)->value('id');
        self::assertSame(1, (int) $day);
    }

    public function test_where_json_contains(): void
    {
        $logins = QueryBuilder::table($this->executor, 'events')
            ->whereJsonContains('payload', ['type' => 'login'])
            ->count();

        self::assertSame(3, $logins);
    }

    public function test_cursor_paginate_keyset(): void
    {
        $page = QueryBuilder::table($this->executor, 'events')->cursorPaginate(2, 'id', 'desc');

        self::assertCount(2, (array) $page->items());
        self::assertTrue($page->hasMorePages());

        $firstPageIds = array_map(static fn (array $row): int => (int) $row['id'], (array) $page->items());
        self::assertSame([4, 3], $firstPageIds);

        $secondPage = QueryBuilder::table($this->executor, 'events')->cursorPaginate(2, 'id', 'desc', 3);
        $secondIds = array_map(static fn (array $row): int => (int) $row['id'], (array) $secondPage->items());
        self::assertSame([2, 1], $secondIds);
    }
}
