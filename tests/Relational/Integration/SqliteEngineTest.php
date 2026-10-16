<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Contracts\EntityInterface;
use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End to end engine test against a real SQLite database (in memory).
 *
 * @group integration
 */
final class SqliteEngineTest extends TestCase
{
    private QueryExecutor $executor;

    private PdoConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-test');
        $this->executor = new QueryExecutor($this->connection, SqliteDialect::make('3.49.2'));

        $this->executor->execute(new RawQuery(
            'create table users (id integer primary key autoincrement, name text not null, age integer not null default 0, active integer not null default 1, meta text null)',
        ));

        $this->executor->execute(new RawQuery('create unique index users_name_unique on users (name)'));
    }

    private function users(): QueryBuilder
    {
        return QueryBuilder::table($this->executor, 'users');
    }

    public function test_insert_select_count_exists_and_order(): void
    {
        $this->users()->insert([
            ['name' => 'ana', 'age' => 30],
            ['name' => 'bob', 'age' => 25],
            ['name' => 'carla', 'age' => 40],
        ]);

        self::assertSame(3, $this->users()->count());
        self::assertTrue($this->users()->where('name', 'ana')->exists());
        self::assertFalse($this->users()->where('name', 'zoe')->exists());

        $rows = $this->users()->select('name', 'age')->orderBy('age', 'asc')->limit(2)->get();

        self::assertSame(['bob', 'ana'], array_column($rows, 'name'));
        self::assertSame([25, 30], array_column($rows, 'age'));
    }

    public function test_object_oriented_expressions_execute(): void
    {
        $this->users()->insert([
            ['name' => 'ana', 'age' => 30],
            ['name' => 'bob', 'age' => 15],
        ]);

        $rows = $this->users()
            ->where(Column::make('age')->gte(18))
            ->select('name')
            ->get();

        self::assertSame([['name' => 'ana']], $rows);
    }

    public function test_light_hydration_and_recordset_interface(): void
    {
        $this->users()->insert([['name' => 'ana', 'age' => 30]]);

        $recordset = $this->users()->light()->recordset();

        self::assertTrue($recordset->exists());
        self::assertSame(1, $recordset->count());
        self::assertInstanceOf(\stdClass::class, $recordset->first());
        self::assertSame('ana', $recordset->first()->name);
        self::assertSame('ana', $recordset->last()->name);
    }

    public function test_entity_hydration(): void
    {
        $this->users()->insert([['name' => 'ana', 'age' => 30]]);

        $entity = $this->users()->entity(SqliteTestUser::class)->first();

        self::assertInstanceOf(SqliteTestUser::class, $entity);
        self::assertSame('ana', $entity->name);
        self::assertSame(30, $entity->age);
    }

    public function test_update_and_delete(): void
    {
        $this->users()->insert([
            ['name' => 'ana', 'age' => 30],
            ['name' => 'bob', 'age' => 25],
        ]);

        $updated = $this->users()->where('name', 'ana')->update(['age' => 31]);
        self::assertSame(1, $updated);
        self::assertSame('31', (string) $this->users()->where('name', 'ana')->value('age'));

        $deleted = $this->users()->where('name', 'bob')->delete();
        self::assertSame(1, $deleted);
        self::assertSame(1, $this->users()->count());
    }

    public function test_upsert_and_returning(): void
    {
        $this->users()->upsert([['name' => 'ana', 'age' => 30]], ['name'], ['age']);

        $returned = $this->executor->rows(
            InsertQuery::into('users')->values(['name' => 'zoe', 'age' => 20])->returning(['id', 'name', 'age']),
        );

        self::assertSame('zoe', $returned[0]['name']);

        $this->users()->upsert([['name' => 'ana', 'age' => 33]], ['name'], ['age']);

        self::assertSame(33, (int) $this->users()->where('name', 'ana')->value('age'));
        self::assertSame(2, $this->users()->count());
    }

    public function test_transaction_rollback(): void
    {
        $transaction = $this->executor->transaction();
        $transaction->begin();

        $this->users()->insert([['name' => 'temp', 'age' => 1]]);
        self::assertSame(1, $this->users()->count());

        $transaction->rollback();

        self::assertSame(0, $this->users()->count());
    }

    public function test_chunk_and_pagination(): void
    {
        $rows = [];
        for ($i = 1; $i <= 25; $i++) {
            $rows[] = ['name' => 'user'.$i, 'age' => $i];
        }
        $this->users()->insert($rows);

        $chunks = 0;
        $this->users()->orderBy('id')->chunk(10, function (array $chunk) use (&$chunks): void {
            $chunks++;
        });
        self::assertSame(3, $chunks);

        $page = $this->users()->orderBy('id')->paginate(10, 2);

        self::assertSame(25, $page->total());
        self::assertSame(2, $page->currentPage());
        self::assertSame(3, $page->lastPage());
        self::assertCount(10, (array) $page->items());
    }

    public function test_destructive_statement_reports_query_exception(): void
    {
        $this->expectException(QueryException::class);
        $this->executor->execute(new RawQuery('delete from missing_table where id = ?', [1]));
    }
}

final class SqliteTestUser implements EntityInterface
{
    public ?int $id = null;

    public ?string $name = null;

    public ?int $age = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? $this->{$name} ?? null;
    }

    public function setAttribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;

        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }

        return $this;
    }

    public function exists(): bool
    {
        return $this->id !== null;
    }
}
