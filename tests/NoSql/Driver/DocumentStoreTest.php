<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Tests\Driver;

use OrmUniversal\NoSql\Collection;
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use OrmUniversal\NoSql\Driver\MongoDbDriver;
use OrmUniversal\NoSql\Driver\RedisDriver;
use PHPUnit\Framework\TestCase;

final class DocumentStoreTest extends TestCase
{
    public function test_store_resolves_collections_per_driver(): void
    {
        $store = new DocumentStore;
        $store->register('memory', new InMemoryDriver, true);

        $users = $store->collection('users');

        self::assertInstanceOf(Collection::class, $users);
        self::assertSame('users', $users->name());
        self::assertSame('in-memory', $users->driver()->name());

        $users->insertMany([['name' => 'ana'], ['name' => 'bob']]);
        self::assertSame(2, $users->count());
        self::assertSame(1, $users->where('name', 'ana')->count());
        self::assertSame('ana', $users->where('name', 'ana')->first()?->get('name'));
    }

    public function test_redis_driver_integration_when_server_available(): void
    {
        if (! RedisDriver::available()) {
            self::markTestSkipped('ext-redis is not available.');
        }

        $driver = new RedisDriver('127.0.0.1', 6379, 'ude_test');
        try {
            $driver->count('probe');
        } catch (\Throwable $exception) {
            self::markTestSkipped('Redis server not reachable: '.$exception->getMessage());
        }

        $driver->drop('users');
        $ids = $driver->insertMany('users', [
            ['name' => 'ana', 'age' => 30],
            ['name' => 'bob', 'age' => 25],
        ]);

        self::assertCount(2, $ids);
        self::assertSame(2, $driver->count('users'));
        self::assertSame(1, $driver->count('users', ['age' => ['$gte' => 30]]));
        self::assertSame('bob', $driver->findOne('users', ['age' => 25])['name']);

        $driver->updateMany('users', ['name' => 'bob'], ['$set' => ['age' => 26]]);
        self::assertSame(26, $driver->findOne('users', ['name' => 'bob'])['age']);

        self::assertSame(1, $driver->deleteMany('users', ['name' => 'ana']));
        self::assertSame(1, $driver->count('users'));

        $driver->drop('users');
        self::assertSame(0, $driver->count('users'));
    }

    public function test_mongodb_driver_integration_when_extension_available(): void
    {
        if (! MongoDbDriver::available()) {
            self::markTestSkipped('ext-mongodb / mongodb/mongodb not available.');
        }

        $driver = new MongoDbDriver('mongodb://127.0.0.1:27017', 'ude_test');
        $driver->drop('probe');
        $driver->insertMany('probe', [['name' => 'ana', 'age' => 30], ['name' => 'bob', 'age' => 25]]);

        self::assertSame(2, $driver->count('probe'));
        self::assertSame('ana', $driver->findOne('probe', ['age' => 30])['name']);

        $driver->drop('probe');
    }
}
