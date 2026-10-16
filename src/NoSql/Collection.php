<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Driver\DocumentDriverInterface;

/**
 * Collection facade over a document driver. Fluent filters, native pipelines.
 *
 * @api public
 */
final class Collection
{
    private ?Filter $filter = null;

    public function __construct(
        private readonly DocumentDriverInterface $driver,
        private readonly string $name,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function driver(): DocumentDriverInterface
    {
        return $this->driver;
    }

    public function filter(): Filter
    {
        return $this->filter ??= Filter::make();
    }

    public function where(string $field, mixed $operator = null, mixed $value = null): self
    {
        $operators = ['=', 'eq', '!=', 'ne', 'neq', '>', 'gt', '>=', 'gte', '<', 'lt', '<=', 'lte', 'in', 'exists', 'regex'];

        if (! is_string($operator) || ! in_array($operator, $operators, true)) {
            return $this->where($field, 'eq', $operator);
        }

        match ($operator) {
            '=', 'eq' => $this->filter()->eq($field, $value),
            '!=', 'ne', 'neq' => $this->filter()->neq($field, $value),
            '>', 'gt' => $this->filter()->gt($field, $value),
            '>=', 'gte' => $this->filter()->gte($field, $value),
            '<', 'lt' => $this->filter()->lt($field, $value),
            '<=', 'lte' => $this->filter()->lte($field, $value),
            'in' => $this->filter()->in($field, (array) $value),
            'exists' => $this->filter()->exists($field, (bool) $value),
            'regex' => $this->filter()->regex($field, (string) $value),
        };

        return $this;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function insert(array $document): int|string
    {
        return $this->insertMany([$document])[0];
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<int|string>
     */
    public function insertMany(array $documents): array
    {
        return $this->driver->insertMany($this->name, $documents);
    }

    /**
     * @return list<Document>
     */
    public function find(): array
    {
        return $this->driver->find($this->name, $this->currentFilter());
    }

    public function first(): ?Document
    {
        $results = $this->driver->find($this->name, $this->currentFilter(), ['limit' => 1]);
        $this->reset();

        return $results[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function update(array $update): int
    {
        $updated = $this->driver->updateMany($this->name, $this->currentFilter(), $update);
        $this->reset();

        return $updated;
    }

    public function delete(): int
    {
        $deleted = $this->driver->deleteMany($this->name, $this->currentFilter());
        $this->reset();

        return $deleted;
    }

    public function count(): int
    {
        return $this->driver->count($this->name, $this->currentFilter());
    }

    public function aggregate(?Pipeline $pipeline = null): array
    {
        if (! $this->driver->supports(Capability::NOSQL_AGGREGATION)) {
            throw new UnsupportedFeatureException(
                feature: Capability::NOSQL_AGGREGATION,
                engine: $this->driver->name(),
                engineVersion: 'n/a',
                reason: 'The driver does not implement aggregation pipelines',
                alternatives: ['Retrieve the documents and aggregate in the application layer'],
            );
        }

        $pipeline ??= Pipeline::make();

        // Prepend the pending fluent filter as a $match stage when present.
        if ($this->filter !== null) {
            $merged = Pipeline::make()->match($this->currentFilter());
            foreach ($pipeline->stages() as $stage) {
                $merged->stage($stage);
            }
            $pipeline = $merged;
        }

        $result = $this->driver->aggregate($this->name, $pipeline);
        $this->reset();

        return $result;
    }

    public function each(callable $callback): void
    {
        foreach ($this->find() as $document) {
            if ($callback($document) === false) {
                break;
            }
        }

        $this->reset();
    }

    public function drop(): void
    {
        $this->driver->drop($this->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function currentFilter(): array
    {
        return $this->filter?->toArray() ?? [];
    }

    private function reset(): void
    {
        $this->filter = null;
    }
}
