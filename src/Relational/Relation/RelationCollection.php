<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

/**
 * Collection of related records for a single parent. Lightweight wrapper used by
 * the relation hydrator when the relation is to-many.
 *
 * @api public
 */
final class RelationCollection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function __construct(
        private readonly string $name,
        private readonly array $records = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->records;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return $this->records[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function last(): ?array
    {
        return $this->records === [] ? null : $this->records[count($this->records) - 1];
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return $this->records === [];
    }

    /**
     * @return array<int|string, list<array<string, mixed>>>
     */
    public function groupBy(string $field): array
    {
        $groups = [];
        foreach ($this->records as $record) {
            $groups[(string) ($record[$field] ?? '')][] = $record;
        }

        return $groups;
    }

    /**
     * @return list<mixed>
     */
    public function pluck(string $field): array
    {
        return array_map(static fn (array $record): mixed => $record[$field] ?? null, $this->records);
    }

    public function sum(string $field): int|float
    {
        return array_sum(array_map(static fn (array $record): float => (float) ($record[$field] ?? 0), $this->records));
    }

    public function avg(string $field): ?float
    {
        if ($this->records === []) {
            return null;
        }

        return $this->sum($field) / count($this->records);
    }

    public function min(string $field): mixed
    {
        if ($this->records === []) {
            return null;
        }

        return min($this->pluck($field));
    }

    public function max(string $field): mixed
    {
        if ($this->records === []) {
            return null;
        }

        return max($this->pluck($field));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->records;
    }

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->records;
    }
}
