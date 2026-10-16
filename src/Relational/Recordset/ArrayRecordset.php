<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

use OrmUniversal\Contracts\PaginatorInterface;
use OrmUniversal\Contracts\RecordsetInterface;
use OrmUniversal\Relational\Result\Paginator;

/**
 * In-memory recordset. Items are already hydrated when they reach this class.
 *
 * @api public
 *
 * @template T
 *
 * @implements RecordsetInterface<T>
 */
class ArrayRecordset implements RecordsetInterface
{
    /** @var list<T> */
    protected array $items;

    private int $position = 0;

    /**
     * @param  list<T>  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function last(): mixed
    {
        return $this->items === [] ? null : $this->items[count($this->items) - 1];
    }

    public function current(): mixed
    {
        return $this->items[$this->position] ?? null;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function previous(): void
    {
        $this->position = max(0, $this->position - 1);
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function exists(): bool
    {
        return $this->items() !== [];
    }

    public function isEmpty(): bool
    {
        return ! $this->exists();
    }

    /**
     * @return list<T>
     */
    public function get(): array
    {
        return $this->items();
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->items());
    }

    public function each(callable $callback): static
    {
        foreach ($this->items() as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    public function map(callable $callback): static
    {
        $mapped = [];
        foreach ($this->items() as $key => $item) {
            $mapped[] = $callback($item, $key);
        }

        return new static($mapped);
    }

    public function filter(?callable $callback = null): static
    {
        if ($callback === null) {
            return new static(array_values(array_filter($this->items())));
        }

        $filtered = [];
        foreach ($this->items() as $key => $item) {
            if ($callback($item, $key)) {
                $filtered[] = $item;
            }
        }

        return new static($filtered);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;
        foreach ($this->items() as $key => $item) {
            $carry = $callback($carry, $item, $key);
        }

        return $carry;
    }

    public function chunk(int $size, callable $callback): void
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        $page = 1;
        foreach (array_chunk($this->items(), $size) as $chunk) {
            if ($callback(new static($chunk), $page) === false) {
                return;
            }
            $page++;
        }
    }

    /**
     * @return \Generator<int, T>
     */
    public function lazy(int $chunkSize = 1000): \Generator
    {
        foreach ($this->items() as $key => $item) {
            yield $key => $item;
        }
    }

    /**
     * @return \Traversable<int, T>
     */
    public function cursor(): \Traversable
    {
        return $this->getIterator();
    }

    /**
     * @return \Traversable<int, T>
     */
    public function stream(): \Traversable
    {
        return $this->getIterator();
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->items();
    }

    public function toCollection(): mixed
    {
        return function_exists('collect') ? collect($this->items()) : $this->items();
    }

    public function paginate(int $perPage = 15, int $page = 1): PaginatorInterface
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $slice = array_slice($this->items(), ($page - 1) * $perPage, $perPage);

        return new Paginator($slice, count($this->items()), $perPage, $page);
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->items() as $key => $item) {
            yield $key => $item;
        }
    }

    /**
     * @return array<int, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->items();
    }

    /**
     * @return list<T>
     */
    protected function items(): array
    {
        return $this->items;
    }
}
