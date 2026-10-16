<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End to end tests for the extended builder API (bulk, policy, inspection).
 *
 * @group integration
 */
final class BuilderApiExtensionsTest extends TestCase
{
    private QueryExecutor $executor;

    protected function setUp(): void
    {
        $connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-ext');
        $this->executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

        $this->executor->execute(new RawQuery(
            'create table users (id integer primary key autoincrement, name text not null unique, age integer not null default 0, active integer not null default 0)',
        ));
    }

    private function users(): QueryBuilder
    {
        return QueryBuilder::table($this->executor, 'users');
    }

    public function test_bulk_insert_update_upsert_and_delete(): void
    {
        $inserted = $this->users()->insertMany([
            ['name' => 'ana', 'age' => 30, 'active' => 1],
            ['name' => 'bob', 'age' => 25, 'active' => 0],
            ['name' => 'carla', 'age' => 40, 'active' => 0],
        ]);

        self::assertSame(3, $inserted);
        self::assertSame(3, $this->users()->count());

        $updated = $this->users()->updateMany(['active' => 1], [1, 2]);
        self::assertSame(2, $updated);
        self::assertSame(2, $this->users()->where('active', true)->count());

        $upserted = $this->users()->upsertMany([
            ['name' => 'ana', 'age' => 31, 'active' => 1],
            ['name' => 'dave', 'age' => 22, 'active' => 1],
        ], ['name'], ['age', 'active']);

        self::assertSame(2, $upserted);
        self::assertSame(4, $this->users()->count());
        self::assertSame(31, (int) $this->users()->where('name', 'ana')->value('age'));

        $deleted = $this->users()->deleteMany([2, 3]);
        self::assertSame(2, $deleted);
        self::assertSame(2, $this->users()->count());
    }

    public function test_destructive_policy_blocks_unbounded_update(): void
    {
        $this->users()->insertMany([['name' => 'ana', 'age' => 30, 'active' => 1]]);

        $this->expectException(QueryException::class);

        $this->users()
            ->usingPolicy(new DestructiveStatementPolicy(DestructiveStatementPolicy::DENY))
            ->update(['active' => 0]);
    }

    public function test_confirmed_destructive_update_is_allowed(): void
    {
        $this->users()->insertMany([['name' => 'ana', 'age' => 30, 'active' => 1]]);

        $updated = $this->users()
            ->usingPolicy(new DestructiveStatementPolicy(DestructiveStatementPolicy::REQUIRE_CONFIRMATION))
            ->confirmDestructive()
            ->update(['active' => 0]);

        self::assertSame(1, $updated);
    }

    public function test_inspection_api(): void
    {
        $this->users()->insertMany([['name' => 'ana', 'age' => 30, 'active' => 1]]);

        $builder = $this->users()->select(Column::make('id'), Column::make('name'))->where('active', true);

        self::assertSame('sqlite-3.49.2', $builder->dialect()->name());
        self::assertTrue($builder->capabilities()->supports('supportsReturning'));
        self::assertSame('SelectQuery', $builder->ast()->nodeType());
        self::assertSame(100.0, $builder->portability()->percent());
        self::assertSame('low', $builder->performance()->estimatedComplexity);

        $rows = $builder->explain();
        self::assertNotEmpty($rows);
    }

    public function test_chunked_streaming_and_recordset_modes(): void
    {
        $rows = [];
        for ($i = 1; $i <= 15; $i++) {
            $rows[] = ['name' => 'user'.$i, 'age' => $i, 'active' => 1];
        }
        $this->users()->insertMany($rows);

        $seen = 0;
        foreach ($this->users()->orderBy('id')->cursor() as $record) {
            $seen++;
        }
        self::assertSame(15, $seen);

        $chunks = 0;
        $this->users()->orderBy('id')->chunk(4, function (array $chunk) use (&$chunks): void {
            $chunks++;
        });
        self::assertSame(4, $chunks);

        self::assertSame(15, $this->users()->light()->recordset()->count());
    }
}
