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
final class CastWithTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

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

    public function test_eloquent_date_casts_match_laravel_json_serialization(): void
    {
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'cast-dates'),
            SqliteDialect::make('3.49.2'),
        );

        $executor->execute(new RawQuery('create table dates (id integer primary key, day text null, moment text null, offset_moment text null, invalid text null)'));
        $executor->execute(new RawQuery("insert into dates (id, day, moment, offset_moment, invalid) values (1, '2023-11-15', '2023-11-15 13:45:30', '2023-11-15T13:45:30-06:00', 'not-a-date')"));
        $executor->execute(new RawQuery('insert into dates (id, day, moment, offset_moment, invalid) values (2, null, null, null, null)'));

        $row = QueryBuilder::table($executor, 'dates')
            ->castWith([
                'day' => 'eloquent_date',
                'moment' => 'eloquent_datetime',
                'offset_moment' => 'eloquent_datetime',
                'invalid' => 'eloquent_json',
            ])
            ->where('id', 1)
            ->first();

        self::assertSame('2023-11-15T00:00:00.000000Z', $row['day']);
        self::assertSame('2023-11-15T13:45:30.000000Z', $row['moment']);
        self::assertSame('2023-11-15T19:45:30.000000Z', $row['offset_moment']);
        self::assertSame('not-a-date', $row['invalid']);

        $json = QueryBuilder::table($executor, 'dates')
            ->castWith([
                'day' => 'eloquent_json',
                'moment' => 'eloquent_json',
                'offset_moment' => 'eloquent_json',
            ])
            ->where('id', 1)
            ->first();

        self::assertSame('2023-11-15T00:00:00.000000Z', $json['day']);
        self::assertSame('2023-11-15T13:45:30.000000Z', $json['moment']);
        self::assertSame('2023-11-15T19:45:30.000000Z', $json['offset_moment']);

        $nulls = QueryBuilder::table($executor, 'dates')
            ->castWith([
                'day' => 'eloquent_date',
                'moment' => 'eloquent_datetime',
                'invalid' => 'eloquent_json',
            ])
            ->where('id', 2)
            ->first();

        self::assertNull($nulls['day']);
        self::assertNull($nulls['moment']);
        self::assertNull($nulls['invalid']);
    }
}
