<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Engine;

use OrmUniversal\Contracts\Enums\NPlusOneMode;
use OrmUniversal\Contracts\Enums\TransactionState;
use OrmUniversal\Contracts\TransactionInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Cache\QueryCache;
use OrmUniversal\Relational\Cache\ResultCache;
use OrmUniversal\Relational\Dialect\MySqlDialect;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Events\N1Detected;
use OrmUniversal\Relational\Events\QueryExecuted;
use OrmUniversal\Relational\Events\TransactionLifecycle;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Execution\RetryPolicy;
use OrmUniversal\Relational\Migration\SchemaOperation;
use OrmUniversal\Relational\Observability\EventEmitter;
use OrmUniversal\Relational\Relation\N1Detector;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

final class PlatformExtrasTest extends TestCase
{
    public function test_result_cache_returns_cached_value_and_bypasses_in_transaction(): void
    {
        $cache = new InMemorySimpleCache;
        $active = $this->transaction(isActive: true);
        $inactive = $this->transaction(isActive: false);

        $reader = new ResultCache($cache, 'ude-test', 60, $inactive);
        $calls = 0;
        $resolver = function () use (&$calls): array {
            $calls++;

            return [['id' => 1]];
        };

        $dialect = SqliteDialect::make('3.49.2');
        $compiled = new CompiledQuery('select * from users');
        $key = $reader->key($dialect, 'test', null, $compiled);

        self::assertSame([['id' => 1]], $reader->remember($key, $resolver));
        self::assertSame([['id' => 1]], $reader->remember($key, $resolver));
        self::assertSame(1, $calls);

        $bypass = new ResultCache($cache, 'ude-test', 60, $active);
        self::assertFalse($bypass->enabled());
        $bypass->remember($key, $resolver);
        self::assertSame(2, $calls, 'Active transactions must bypass the result cache.');
    }

    public function test_compiled_query_cache_reuses_the_compiled_object(): void
    {
        $cache = new QueryCache(new InMemorySimpleCache, 'ude-test');
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'cache-test'),
            SqliteDialect::make('3.49.2'),
        )->withQueryCache($cache);

        $query = SelectQuery::make('users')->select('id')->where('active', true);

        $first = $executor->compile($query);
        $second = $executor->compile($query);

        self::assertSame($first, $second);
    }

    public function test_compiled_query_cache_distinguishes_projections_and_aliases(): void
    {
        $cache = new QueryCache(new InMemorySimpleCache, 'ude-test');
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'cache-test'),
            SqliteDialect::make('3.49.2'),
        )->withQueryCache($cache);

        $id = $executor->compile(SelectQuery::make('users')->select('id'));
        $name = $executor->compile(SelectQuery::make('users')->select('name'));
        $filtered = $executor->compile(SelectQuery::make('users')->select('id')->where('active', true));

        self::assertNotSame($id->sql, $name->sql);
        self::assertNotSame($id->sql, $filtered->sql);
        self::assertSame('select "id" from "users"', $id->sql);
    }

    public function test_retry_policy_retries_idempotent_deadlocks_only(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3, baseDelayMs: 1);
        $attempts = 0;

        $result = $policy->run(function () use (&$attempts): string {
            $attempts++;
            if ($attempts < 2) {
                throw new \RuntimeException('Deadlock found when trying to get lock');
            }

            return 'ok';
        }, idempotent: true);

        self::assertSame('ok', $result);
        self::assertSame(2, $attempts);

        $attempts = 0;
        try {
            $policy->run(function () use (&$attempts): void {
                $attempts++;
                throw new \RuntimeException('Deadlock found');
            }, idempotent: false);
            self::fail('Expected exception.');
        } catch (\RuntimeException) {
            self::assertSame(1, $attempts, 'Non-idempotent operations are never retried.');
        }

        self::assertTrue(RetryPolicy::isRetryable(new \RuntimeException('SQLSTATE[40P01]: deadlock detected')));
        self::assertFalse(RetryPolicy::isRetryable(new \RuntimeException('Syntax error')));
    }

    public function test_migration_warnings_are_dialect_specific(): void
    {
        $mysql = MySqlDialect::make('8.4.3');
        $sqlite = SqliteDialect::make('3.49.2');

        self::assertNotEmpty($mysql->migrationWarnings(SchemaOperation::addIndex('users', new IndexDefinition('idx', ['a']))));
        self::assertSame([], $sqlite->migrationWarnings(SchemaOperation::dropTable('users')));
    }

    public function test_events_are_emitted_through_the_emitter(): void
    {
        $dispatcher = new CollectingDispatcher;
        $connection = new PdoConnection(
            new PDO('sqlite::memory:'),
            'events-test',
            null,
            new EventEmitter($dispatcher),
        );

        $executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));
        $executor->execute(new RawQuery('create table events_probe (id integer primary key)'));

        foreach ($connection->select(new CompiledQuery('select 1 as ok')) as $row) {
            // Drain the cursor.
        }

        $transaction = $connection->transaction();
        $transaction->begin();
        $transaction->commit();

        $types = array_map(static fn (object $event): string => $event::class, $dispatcher->events);

        self::assertContains(QueryExecuted::class, $types);
        self::assertContains(TransactionLifecycle::class, $types);

        $lifecycle = array_values(array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof TransactionLifecycle));
        self::assertSame(TransactionState::Committed, $lifecycle[0]->state);
    }

    public function test_n1_detector_emits_events(): void
    {
        $dispatcher = new CollectingDispatcher;
        $detector = new N1Detector(
            mode: NPlusOneMode::Telemetry,
            threshold: 2,
            events: new EventEmitter($dispatcher),
        );

        $detector->record('orders', 5);
        $detector->record('orders', 5);

        self::assertCount(1, array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof N1Detected));
    }

    private function transaction(bool $isActive): TransactionInterface
    {
        return new class($isActive) implements TransactionInterface
        {
            public function __construct(private readonly bool $active) {}

            public function begin(): void {}

            public function commit(): void {}

            public function rollback(): void {}

            public function supportsSavepoints(): bool
            {
                return true;
            }

            public function savepoint(string $name): void {}

            public function rollbackTo(string $name): void {}

            public function releaseSavepoint(string $name): void {}

            public function state(): TransactionState
            {
                return $this->active ? TransactionState::Active : TransactionState::None;
            }

            public function level(): int
            {
                return 1;
            }

            public function isActive(): bool
            {
                return $this->active;
            }
        };
    }
}

final class InMemorySimpleCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }
}

final class CollectingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
