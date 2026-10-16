<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * High performance result set. Implementations decide the storage strategy:
 * array, object, entity, streaming, lazy or cursor.
 *
 * @api public
 *
 * @template T
 *
 * @extends \IteratorAggregate<int, T>
 */
interface RecordsetInterface extends \Countable, \IteratorAggregate, \JsonSerializable
{
    public function first(): mixed;

    public function last(): mixed;

    public function current(): mixed;

    public function next(): void;

    public function previous(): void;

    public function count(): int;

    public function exists(): bool;

    public function isEmpty(): bool;

    /**
     * @return list<T>
     */
    public function get(): array;

    public function has(int|string $key): bool;

    public function each(callable $callback): static;

    public function map(callable $callback): static;

    public function filter(?callable $callback = null): static;

    public function reduce(callable $callback, mixed $initial = null): mixed;

    public function chunk(int $size, callable $callback): void;

    /**
     * @return \Generator<int, T>
     */
    public function lazy(int $chunkSize = 1000): \Generator;

    /**
     * @return \Traversable<int, T>
     */
    public function cursor(): \Traversable;

    /**
     * @return \Traversable<int, T>
     */
    public function stream(): \Traversable;

    /**
     * @return list<T>
     */
    public function toArray(): array;

    /**
     * Framework collection when an adapter is available, array otherwise.
     */
    public function toCollection(): mixed;

    public function paginate(int $perPage = 15, int $page = 1): PaginatorInterface;

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable;
}
