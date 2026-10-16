<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Observability\InMemoryProfiler;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/* */
#[Group('integration')]
final class CountFastPathTest extends TestCase
{
    private QueryExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'count-test'),
            SqliteDialect::make('3.49.2'),
        );

        $this->executor->execute(new RawQuery('create table items (id integer primary key, kind text not null, qty integer not null)'));
        $this->executor->execute(new RawQuery("insert into items (id, kind, qty) values (1,'a',5),(2,'a',7),(3,'b',2),(4,'b',9),(5,'c',1)"));
    }

    public function test_simple_count_uses_direct_aggregate_without_wrapper(): void
    {
        $profiler = new InMemoryProfiler;
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'count-sql', $profiler),
            SqliteDialect::make('3.49.2'),
        );

        $executor->execute(new RawQuery('create table t (id integer primary key, kind text not null)'));
        $executor->execute(new RawQuery("insert into t (id, kind) values (1,'a'),(2,'a'),(3,'b')"));

        QueryBuilder::table($executor, 't')->where('kind', 'a')->count();
        $events = $profiler->events();
        $direct = end($events)->sql;

        self::assertStringStartsWith('select COUNT(*)', $direct);
        self::assertStringNotContainsString('from (select', strtolower($direct));

        $profiler->reset();
        QueryBuilder::table($executor, 't')->select('kind')->groupBy('kind')->count();
        $events = $profiler->events();
        $wrapped = end($events)->sql;

        self::assertStringContainsString('from (select', strtolower($wrapped));
    }

    public function test_group_and_distinct_counts_fall_back_to_wrapper(): void
    {
        $grouped = QueryBuilder::table($this->executor, 'items')->select('kind')->groupBy('kind');
        self::assertSame(3, $grouped->count());

        $distinct = QueryBuilder::table($this->executor, 'items')->select('kind')->distinct();
        self::assertSame(3, $distinct->count());
    }

    public function test_count_matches_row_iteration(): void
    {
        foreach ([null, 'a', 'b'] as $kind) {
            $builder = QueryBuilder::table($this->executor, 'items');
            if ($kind !== null) {
                $builder->where('kind', $kind);
            }

            self::assertSame(count($builder->get()), $builder->count());
        }
    }
}
