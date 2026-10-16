<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Tests\Driver;

use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use PHPUnit\Framework\TestCase;

final class InMemoryDriverTest extends TestCase
{
    private InMemoryDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new InMemoryDriver;
    }

    public function test_crud_and_filters(): void
    {
        $ids = $this->driver->insertMany('users', [
            ['name' => 'ana', 'age' => 30, 'city' => 'mx'],
            ['name' => 'bob', 'age' => 25, 'city' => 'mx'],
            ['name' => 'zoe', 'age' => 40, 'city' => 'us'],
        ]);

        self::assertCount(3, $ids);
        self::assertSame(3, $this->driver->count('users'));
        self::assertSame(2, $this->driver->count('users', ['city' => 'mx']));
        self::assertSame(1, $this->driver->count('users', ['age' => ['$gte' => 30], 'city' => 'mx']));

        $found = $this->driver->find('users', ['age' => ['$gt' => 20]], ['sort' => ['age' => -1], 'limit' => 2]);
        self::assertSame('zoe', $found[0]->get('name'));
        self::assertSame('ana', $found[1]->get('name'));

        $updated = $this->driver->updateMany('users', ['name' => 'ana'], ['$set' => ['city' => 'gdl']]);
        self::assertSame(1, $updated);
        self::assertSame('gdl', $this->driver->findOne('users', ['name' => 'ana'])['city']);

        $deleted = $this->driver->deleteMany('users', ['city' => 'us']);
        self::assertSame(1, $deleted);
        self::assertSame(2, $this->driver->count('users'));
    }

    public function test_document_magic_access_and_immutability(): void
    {
        $document = Document::fromArray(['name' => 'ana', 'meta' => ['x' => 1]]);

        self::assertSame('ana', $document->name);
        self::assertNull($document->missing);
        self::assertSame('ana', $document['name']);
        self::assertTrue(isset($document['meta']));
        self::assertSame('fallback', $document->get('nope', 'fallback'));
        self::assertSame(['name' => 'ana', 'meta' => ['x' => 1]], $document->toArray());
        self::assertSame('ana', json_decode((string) json_encode($document), true)['name']);

        $this->expectException(\LogicException::class);
        $document['name'] = 'bob';
    }

    public function test_or_filter_and_dot_notation(): void
    {
        $this->driver->insertMany('events', [
            ['name' => 'a', 'meta' => ['level' => 1]],
            ['name' => 'b', 'meta' => ['level' => 5]],
            ['name' => 'c'],
        ]);

        $filter = ['meta.level' => ['$gte' => 2]];
        self::assertSame(1, $this->driver->count('events', $filter));

        $orFilter = ['$or' => [['name' => 'a'], ['name' => 'c']]];
        self::assertSame(2, $this->driver->count('events', $orFilter));
    }

    public function test_aggregation_pipeline_with_group(): void
    {
        $this->driver->insertMany('orders', [
            ['customer' => 'ana', 'total' => 100, 'status' => 'paid'],
            ['customer' => 'ana', 'total' => 50, 'status' => 'paid'],
            ['customer' => 'bob', 'total' => 30, 'status' => 'pending'],
        ]);

        $result = $this->driver->aggregate('orders', Pipeline::make()
            ->match(['status' => 'paid'])
            ->group(['_id' => '$customer', 'revenue' => ['$sum' => '$total'], 'orders' => ['$count' => 1]])
            ->sort(['revenue' => -1]));

        self::assertSame('ana', $result[0]['_id']);
        self::assertSame(150.0, (float) $result[0]['revenue']);
        self::assertSame(2, $result[0]['orders']);
    }

    public function test_pipeline_limit_skip_project_unwind(): void
    {
        $this->driver->insertMany('items', [
            ['name' => 'a', 'tags' => ['x', 'y']],
            ['name' => 'b', 'tags' => ['z']],
        ]);

        $unwound = $this->driver->aggregate('items', Pipeline::make()->unwind('$tags')->project(['tags' => 1]));
        self::assertCount(3, $unwound);

        $limited = $this->driver->aggregate('items', Pipeline::make()->sort(['name' => 1])->skip(1)->limit(1)->project(['name' => 1]));
        self::assertSame('b', array_values($limited[0])[0]);
    }

    public function test_capabilities(): void
    {
        self::assertTrue($this->driver->supports('supportsAggregation'));
        self::assertTrue($this->driver->supports('supportsPipeline'));
        self::assertFalse($this->driver->supports('supportsVectorSearch'));
    }
}
