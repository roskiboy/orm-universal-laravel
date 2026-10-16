<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
final class CastWithTest extends TestCase
{
    private function executor(): QueryExecutor
    {
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'cast-test'),
            SqliteDialect::make('3.49.2'),
        );

        $executor->execute(new RawQuery('create table t (id integer primary key, price text not null, meta text null, active integer not null, embedding text null)'));
        $executor->execute(new RawQuery("insert into t (id, price, meta, active, embedding) values (1, '10.50', '{\"a\":1}', 1, '[1, 2.5]')"));

        return $executor;
    }

    public function test_cast_with_applies_casts_to_get_and_first(): void
    {
        $builder = QueryBuilder::table($this->executor(), 't')
            ->castWith(['price' => 'decimal:2', 'meta' => 'array', 'active' => 'bool', 'embedding' => 'vector']);

        $row = $builder->first();

        self::assertSame('10.50', $row['price']);
        self::assertSame(['a' => 1], $row['meta']);
        self::assertTrue($row['active']);
        self::assertSame([1.0, 2.5], $row['embedding']);

        $rows = $builder->get();
        self::assertSame('10.50', $rows[0]['price']);
        self::assertSame(['a' => 1], $rows[0]['meta']);
    }

    public function test_without_casts_rows_stay_raw(): void
    {
        $row = QueryBuilder::table($this->executor(), 't')->first();

        self::assertSame('{"a":1}', $row['meta']);
        self::assertSame(1, $row['active']);
    }
}
