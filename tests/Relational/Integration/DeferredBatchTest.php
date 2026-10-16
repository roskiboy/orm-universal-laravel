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
final class DeferredBatchTest extends TestCase
{
    private function executor(InMemoryProfiler $profiler): QueryExecutor
    {
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'defer-test', $profiler),
            SqliteDialect::make('3.49.2'),
        );

        $executor->execute(new RawQuery('create table t (id integer primary key, kind text not null)'));
        $executor->execute(new RawQuery("insert into t (id, kind) values (1,'a'),(2,'a'),(3,'b')"));

        return $executor;
    }

    public function test_deferred_duplicates_are_resolved_once(): void
    {
        $profiler = new InMemoryProfiler;
        $executor = $this->executor($profiler);

        $first = QueryBuilder::table($executor, 't')->where('kind', 'a')->defer();
        $duplicate = QueryBuilder::table($executor, 't')->where('kind', 'a')->defer();
        $other = QueryBuilder::table($executor, 't')->where('kind', 'b')->defer();

        self::assertSame(2, $executor->deferred()->pending());
        self::assertFalse($first->settled());

        $profiler->reset();

        $row = $first->resolve();
        $same = $duplicate->resolve();
        $different = $other->resolve();

        self::assertSame('a', $row['kind']);
        self::assertSame($row, $same);
        self::assertSame('b', $different['kind']);
        self::assertTrue($first->settled());
        self::assertSame(4, count($profiler->events()));
        self::assertSame(0, $executor->deferred()->pending());
    }

    public function test_flush_deferred_executes_at_request_boundary(): void
    {
        $profiler = new InMemoryProfiler;
        $executor = $this->executor($profiler);

        $result = QueryBuilder::table($executor, 't')->where('kind', 'b')->defer();
        self::assertSame(1, $executor->deferred()->pending());

        $profiler->reset();
        $executor->flushDeferred();

        self::assertTrue($result->settled());
        self::assertSame('b', $result->resolve()['kind']);
        self::assertSame(2, count($profiler->events()));
        self::assertSame(1, count(array_unique(array_map(static fn ($event): string => $event->sql, $profiler->events()))));
    }

    public function test_query_reuse_is_opt_in(): void
    {
        $profiler = new InMemoryProfiler;
        $executor = $this->executor($profiler);

        $profiler->reset();
        QueryBuilder::table($executor, 't')->where('kind', 'a')->get();
        QueryBuilder::table($executor, 't')->where('kind', 'a')->get();
        self::assertSame(4, count($profiler->events()));

        $profiler->reset();
        $executor->withQueryReuse(true);

        $first = QueryBuilder::table($executor, 't')->where('kind', 'a')->get();
        $second = QueryBuilder::table($executor, 't')->where('kind', 'a')->get();

        self::assertSame($first, $second);
        self::assertSame(2, count($profiler->events()));

        $executor->flushReuse();
        QueryBuilder::table($executor, 't')->where('kind', 'a')->get();
        self::assertSame(4, count($profiler->events()));
    }
}
