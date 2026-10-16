<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Driver;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;

/**
 * Fully testable in-memory document driver. Serves as the contract reference
 * implementation and as a cache/testing backend.
 *
 * @api public
 */
final class InMemoryDriver implements DocumentDriverInterface
{
    /** @var array<string, array<int|string, array<string, mixed>>> */
    private array $collections = [];

    private int $sequence = 0;

    private readonly FilterMatcher $matcher;

    public function __construct()
    {
        $this->matcher = new FilterMatcher;
    }

    public function name(): string
    {
        return 'in-memory';
    }

    public function capabilities(): CapabilitySet
    {
        return CapabilitySet::of([
            Capability::NOSQL_JSON_DOCUMENTS,
            Capability::NOSQL_AGGREGATION,
            Capability::NOSQL_PIPELINE,
            Capability::NOSQL_TRANSACTIONS,
        ]);
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities()->supports($capability);
    }

    public function insertMany(string $collection, array $documents): array
    {
        $ids = [];

        foreach ($documents as $document) {
            $id = $document['_id'] ?? $document['id'] ?? ++$this->sequence;
            $this->collections[$collection][$id] = array_merge(['_id' => $id], $document);
            $ids[] = $id;
        }

        return $ids;
    }

    public function find(string $collection, array $filter = [], array $options = []): array
    {
        $documents = [];
        foreach ($this->collections[$collection] ?? [] as $document) {
            if ($this->matcher->matches($document, $filter)) {
                $documents[] = $document;
            }
        }

        if (isset($options['sort'])) {
            usort($documents, function (array $left, array $right) use ($options): int {
                foreach ($options['sort'] as $field => $direction) {
                    $comparison = ($left[$field] ?? null) <=> ($right[$field] ?? null);
                    if ($comparison !== 0) {
                        return $direction >= 0 ? $comparison : -$comparison;
                    }
                }

                return 0;
            });
        }

        if (isset($options['skip'])) {
            $documents = array_slice($documents, max(0, (int) $options['skip']));
        }

        if (isset($options['limit'])) {
            $documents = array_slice($documents, 0, max(0, (int) $options['limit']));
        }

        return array_map(static fn (array $document): Document => Document::fromArray($document), $documents);
    }

    public function findOne(string $collection, array $filter = []): ?array
    {
        foreach ($this->collections[$collection] ?? [] as $document) {
            if ($this->matcher->matches($document, $filter)) {
                return $document;
            }
        }

        return null;
    }

    public function updateMany(string $collection, array $filter, array $update): int
    {
        $updated = 0;
        foreach ($this->collections[$collection] ?? [] as $id => $document) {
            if (! $this->matcher->matches($document, $filter)) {
                continue;
            }

            $this->collections[$collection][$id] = array_merge($document, $this->applyUpdate($document, $update));
            $updated++;
        }

        return $updated;
    }

    public function deleteMany(string $collection, array $filter): int
    {
        $deleted = 0;
        foreach ($this->collections[$collection] ?? [] as $id => $document) {
            if ($this->matcher->matches($document, $filter)) {
                unset($this->collections[$collection][$id]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function count(string $collection, array $filter = []): int
    {
        return count($this->find($collection, $filter));
    }

    public function aggregate(string $collection, Pipeline $pipeline): array
    {
        $documents = array_map(static fn (array $document): array => $document, $this->collections[$collection] ?? []);

        foreach ($pipeline->stages() as $stage) {
            foreach ($stage as $operator => $operand) {
                $documents = match ($operator) {
                    '$match' => array_values(array_filter($documents, fn (array $document): bool => $this->matcher->matches($document, (array) $operand))),
                    '$sort' => $this->sort($documents, (array) $operand),
                    '$skip' => array_slice($documents, max(0, (int) $operand)),
                    '$limit' => array_slice($documents, 0, max(0, (int) $operand)),
                    '$project' => array_map(fn (array $document): array => $this->project($document, (array) $operand), $documents),
                    '$group' => $this->group($documents, (array) $operand),
                    '$unwind' => $this->unwind($documents, (string) $operand),
                    default => throw new \InvalidArgumentException("Unsupported aggregation stage [{$operator}]."),
                };
            }
        }

        return $documents;
    }

    public function drop(string $collection): void
    {
        unset($this->collections[$collection]);
    }

    public function collections(): array
    {
        return array_keys($this->collections);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    private function applyUpdate(array $document, array $update): array
    {
        if (isset($update['$set'])) {
            return (array) $update['$set'];
        }

        if (isset($update['$inc'])) {
            $changes = [];
            foreach ((array) $update['$inc'] as $field => $amount) {
                $changes[$field] = ($document[$field] ?? 0) + $amount;
            }

            return $changes;
        }

        // Plain replacement update.
        unset($update['_id']);

        return $update;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  array<string, 1|-1>  $fields
     * @return list<array<string, mixed>>
     */
    private function sort(array $documents, array $fields): array
    {
        usort($documents, function (array $left, array $right) use ($fields): int {
            foreach ($fields as $field => $direction) {
                $comparison = ($left[$field] ?? null) <=> ($right[$field] ?? null);
                if ($comparison !== 0) {
                    return $direction >= 0 ? $comparison : -$comparison;
                }
            }

            return 0;
        });

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function project(array $document, array $spec): array
    {
        $result = [];
        foreach ($spec as $field => $included) {
            if ($included) {
                $result[$field] = $document[$field] ?? null;
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  array<string, mixed>  $spec
     * @return list<array<string, mixed>>
     */
    private function group(array $documents, array $spec): array
    {
        $idExpression = $spec['_id'] ?? null;
        $groups = [];

        foreach ($documents as $document) {
            $key = match (true) {
                $idExpression === null => 'null',
                is_string($idExpression) && str_starts_with($idExpression, '$') => (string) ($document[substr($idExpression, 1)] ?? 'null'),
                default => json_encode($idExpression),
            };

            $groups[$key][] = $document;
        }

        $result = [];
        foreach ($groups as $key => $rows) {
            $group = ['_id' => $key === 'null' ? null : $key];

            foreach ($spec as $field => $accumulator) {
                if ($field === '_id' || ! is_array($accumulator)) {
                    continue;
                }

                foreach ($accumulator as $operator => $expression) {
                    $group[$field] = match ($operator) {
                        '$sum' => array_sum(array_map(fn (array $row): float => (float) ($row[substr((string) $expression, 1)] ?? $expression), $rows)),
                        '$avg' => count($rows) === 0 ? 0 : array_sum(array_map(fn (array $row): float => (float) ($row[substr((string) $expression, 1)] ?? 0), $rows)) / count($rows),
                        '$min' => min(array_map(fn (array $row) => $row[substr((string) $expression, 1)] ?? null, $rows)),
                        '$max' => max(array_map(fn (array $row) => $row[substr((string) $expression, 1)] ?? null, $rows)),
                        '$count' => count($rows),
                        '$first' => $rows[0][substr((string) $expression, 1)] ?? null,
                        default => throw new \InvalidArgumentException("Unsupported accumulator [{$operator}]."),
                    };
                }
            }

            $result[] = $group;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    private function unwind(array $documents, string $path): array
    {
        $field = ltrim($path, '$');
        $result = [];

        foreach ($documents as $document) {
            $values = $document[$field] ?? [];
            if (! is_array($values)) {
                $result[] = $document;

                continue;
            }

            foreach ($values as $value) {
                $result[] = array_merge($document, [$field => $value]);
            }
        }

        return $result;
    }
}
