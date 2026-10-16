<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Cache\CompiledPlanMemory;
use OrmUniversal\Relational\Cache\QueryCache;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class PlanMemoryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        CompiledPlanMemory::flush();
    }

    protected function tearDown(): void
    {
        CompiledPlanMemory::flush();
    }

    public function test_memory_layer_avoids_remote_lookup_for_repeated_plans(): void
    {
        $store = new CountingCache;
        $executor = $this->executor($store);

        $first = $executor->compile($this->queryFor('2026-01-01'));
        $second = $executor->compile($this->queryFor('2026-01-01'));

        self::assertSame($first, $second, 'Repeated compiles must reuse the in-memory plan instance');
        self::assertSame(1, $store->gets, 'The remote cache must only be consulted once');
        self::assertSame(1, $store->puts);
        self::assertSame(1, CompiledPlanMemory::count());
    }

    public function test_distinct_shapes_hit_the_remote_cache_independently(): void
    {
        $store = new CountingCache;
        $executor = $this->executor($store);

        $executor->compile($this->queryFor('2026-01-01'));
        $executor->compile($this->queryFor('2026-02-02', 'id'));

        self::assertSame(2, $store->gets);
        self::assertSame(2, $store->puts);
        self::assertSame(2, CompiledPlanMemory::count());
    }

    public function test_remote_hit_warms_the_memory_layer(): void
    {
        $seed = new CountingCache;
        $seeded = $this->executor($seed);
        $warm = $seeded->compile($this->queryFor('2026-01-01'));

        // Drop only the memory layer; the remote store keeps the plan.
        CompiledPlanMemory::flush();

        $store = new CountingCache($seed->items);
        $executor = $this->executor($store);

        $plan = $executor->compile($this->queryFor('2026-01-01'));

        self::assertSame($warm->sql, $plan->sql, 'A remote hit must return the same compiled SQL');
        self::assertSame(1, $store->gets, 'The remote lookup happens exactly once');
        self::assertSame(0, $store->puts, 'A remote hit must not write back');
        self::assertSame(1, CompiledPlanMemory::count(), 'The remote hit must warm the memory layer');

        $again = $executor->compile($this->queryFor('2026-01-01'));
        self::assertSame($plan, $again, 'After the remote hit the memory layer serves the plan');
        self::assertSame(1, $store->gets);
    }

    public function test_memory_store_is_bounded_and_flushable(): void
    {
        for ($i = 0; $i < CompiledPlanMemory::LIMIT + 40; $i++) {
            CompiledPlanMemory::put('plan-'.$i, $this->executor(new CountingCache)->compile($this->queryFor('2026-01-01')));
        }

        self::assertLessThanOrEqual(CompiledPlanMemory::LIMIT, CompiledPlanMemory::count());

        CompiledPlanMemory::flush();
        self::assertSame(0, CompiledPlanMemory::count());

        QueryExecutor::flushPlanMemory();
        self::assertSame(0, CompiledPlanMemory::count());
    }

    public function test_compile_without_plan_cache_still_returns_plans(): void
    {
        $executor = new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'plan-memory-test'),
            SqliteDialect::make('3.49.2'),
        );

        $plan = $executor->compile($this->queryFor('2026-01-01'));

        self::assertStringContainsString('where', strtolower($plan->sql));
        self::assertSame(0, CompiledPlanMemory::count());
    }

    private function executor(CacheInterface $store): QueryExecutor
    {
        return new QueryExecutor(
            new PdoConnection(new PDO('sqlite::memory:'), 'plan-memory-test'),
            SqliteDialect::make('3.49.2'),
            null,
            new QueryCache($store, 'ude', 60),
        );
    }

    private function queryFor(string $value, string $column = 'date'): SelectQuery
    {
        return SelectQuery::make('users')->replaceWhere(Column::make($column)->eq($value));
    }
}

/**
 * Minimal PSR-16 spy: counts reads/writes and exposes the stored items so a
 * second cache instance can simulate a warm remote store.
 */
final class CountingCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $items = [];

    public int $gets = 0;

    public int $puts = 0;

    /**
     * @param  array<string, mixed>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->gets++;

        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $this->puts++;
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
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
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
