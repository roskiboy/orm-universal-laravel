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
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
final class ExistsProjectionTest extends TestCase
{
    private function executor(InMemoryProfiler $profiler): QueryExecutor
    {
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'exists-test', $profiler),
            SqliteDialect::make('3.49.2'),
        );

        $executor->execute(new RawQuery('create table t (id integer primary key, kind text not null)'));
        $executor->execute(new RawQuery("insert into t (id, kind) values (1,'a'),(2,'a'),(3,'b')"));

        return $executor;
    }

    public function test_exists_uses_direct_projection_on_sqlite(): void
    {
        $profiler = new InMemoryProfiler;
        $executor = $this->executor($profiler);

        self::assertTrue(QueryBuilder::table($executor, 't')->where('kind', 'a')->exists());
        self::assertFalse(QueryBuilder::table($executor, 't')->where('kind', 'z')->exists());

        $events = $profiler->events();
        $direct = end($events)->sql;

        self::assertStringStartsWith('select exists(', $direct);
        self::assertStringNotContainsString('ude_exists_check', $direct);
        self::assertStringNotContainsString('count(', strtolower($direct));
    }

    public function test_exists_matches_row_iteration_and_negation(): void
    {
        $profiler = new InMemoryProfiler;
        $executor = $this->executor($profiler);

        foreach (['a', 'b', 'z'] as $kind) {
            $builder = QueryBuilder::table($executor, 't')->where('kind', $kind);

            self::assertSame($builder->get() !== [], $builder->exists());
            self::assertSame($builder->get() === [], $builder->doesntExist());
        }

        self::assertTrue(QueryBuilder::table($executor, 't')->doesntExist() === false);
    }
}
