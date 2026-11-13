<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as LaravelSimplePaginator;
use OrmUniversal\Laravel\Pagination\LaravelPagination;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;

final class LaravelPaginationTest extends TestCase
{
    private function executor(): QueryExecutor
    {
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'pagination-test'),
            SqliteDialect::make('3.49.2'),
        );

        $executor->execute(new RawQuery('create table items (id integer primary key, name text not null)'));
        $executor->execute(new RawQuery("insert into items (id, name) values (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')"));

        return $executor;
    }

    public function test_adapter_returns_a_native_length_aware_paginator(): void
    {
        $paginator = LaravelPagination::paginate(
            QueryBuilder::table($this->executor(), 'items')->orderBy('id'),
            2,
            2,
            '/items',
            ['page' => 2, 'filter' => 'x'],
        );

        self::assertInstanceOf(LengthAwarePaginator::class, $paginator);
        self::assertSame(5, $paginator->total());
        self::assertSame(2, $paginator->perPage());
        self::assertSame(2, $paginator->currentPage());
        self::assertSame(3, $paginator->lastPage());
        self::assertSame([3, 4], array_column($paginator->items(), 'id'));
        self::assertStringContainsString('page=3', $paginator->url(3));
        self::assertStringContainsString('filter=x', $paginator->url(3));
    }

    public function test_macros_are_registered_and_usable(): void
    {
        LaravelPagination::register();

        self::assertTrue(QueryBuilder::hasMacro('paginateLaravel'));
        self::assertTrue(QueryBuilder::hasMacro('simplePaginateLaravel'));

        $paginator = QueryBuilder::table($this->executor(), 'items')
            ->orderBy('id')
            ->paginateLaravel(2, 1, '/items', []);

        self::assertInstanceOf(LengthAwarePaginator::class, $paginator);
        self::assertSame(5, $paginator->total());
        self::assertSame([1, 2], array_column($paginator->items(), 'id'));
    }

    public function test_zero_hydration_pagination_keeps_arrays(): void
    {
        $paginator = LaravelPagination::paginate(
            QueryBuilder::table($this->executor(), 'items')->orderBy('id')->zeroHydration(),
            2,
            1,
            '/items',
            [],
        );

        self::assertIsArray($paginator->items()[0]);
        self::assertSame(1, $paginator->items()[0]['id']);
        self::assertSame('a', $paginator->items()[0]['name']);
    }

    public function test_light_hydration_pagination_keeps_objects(): void
    {
        $paginator = LaravelPagination::paginate(
            QueryBuilder::table($this->executor(), 'items')->orderBy('id')->light(),
            2,
            1,
            '/items',
            [],
        );

        self::assertIsObject($paginator->items()[0]);
        self::assertSame(1, $paginator->items()[0]->id);
    }

    public function test_simple_paginator_variant(): void
    {
        $paginator = LaravelPagination::simplePaginate(
            QueryBuilder::table($this->executor(), 'items')->orderBy('id'),
            2,
            2,
            '/items',
            [],
        );

        self::assertInstanceOf(LaravelSimplePaginator::class, $paginator);
        self::assertSame(2, $paginator->currentPage());
        self::assertSame([3, 4], array_column($paginator->items(), 'id'));

        LaravelPagination::register();
        $macro = QueryBuilder::table($this->executor(), 'items')
            ->orderBy('id')
            ->simplePaginateLaravel(2, 1, '/items', []);

        self::assertInstanceOf(LaravelSimplePaginator::class, $macro);
        self::assertSame([1, 2], array_column($macro->items(), 'id'));
    }
}
