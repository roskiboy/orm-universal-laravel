<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\EntityInterface;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Exceptions\RelationException;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Builder\QueryBuilder;

/**
 * Batch relation loader: one query per relation for the whole parent set.
 *
 * @api public
 */
final class RelationBatchLoader
{
    /**
     * Batch aggregate: one grouped query per relation (no N+1, no correlated
     * subqueries).
     *
     * @param  iterable<int, mixed>  $parents
     * @return array<int|string, int|float|string|null> parent key => aggregate value
     */
    public function loadAggregate(
        iterable $parents,
        RelationMetadata $relation,
        string $function,
        string $column = '*',
        ?callable $builderFactory = null,
        bool $distinct = false,
    ): array {
        $keys = [];
        foreach ($parents as $parent) {
            $key = $this->readKey($parent, $relation->localKey());
            if ($key !== null) {
                $keys[(string) $key] = true;
            }
        }

        if ($keys === []) {
            return [];
        }

        if ($builderFactory === null) {
            throw new RelationException('loadAggregate requires a builder factory for the related table.');
        }

        $related = $relation->relatedTable()->name;

        /** @var QueryBuilder $builder */
        $builder = $builderFactory($related);

        if (! $builder instanceof QueryBuilder) {
            throw new RelationException('The relation builder factory must return a QueryBuilder.');
        }

        $aggregate = new Aggregate(
            match (strtolower($function)) {
                'count' => $distinct ? AggregateFunction::CountDistinct : AggregateFunction::Count,
                'sum' => AggregateFunction::Sum,
                'avg' => AggregateFunction::Avg,
                'min' => AggregateFunction::Min,
                'max' => AggregateFunction::Max,
                default => throw new RelationException('Unsupported aggregate ['.$function.'].'),
            },
            $column === '*' ? '*' : Column::of($related, $column),
        );

        $rows = $builder
            ->select($relation->foreignKey(), $aggregate->as('ude_aggregate'))
            ->whereIn($relation->foreignKey(), array_keys($keys))
            ->groupBy($relation->foreignKey())
            ->get();

        $values = [];
        foreach ($rows as $row) {
            $foreign = $row[$relation->foreignKey()] ?? null;
            if ($foreign === null) {
                continue;
            }

            $values[(string) $foreign] = $row['ude_aggregate'] ?? null;
        }

        return $values;
    }

    /**
     * @param  iterable<int, mixed>  $parents
     * @return array<int|string, list<array<string, mixed>>> rows keyed by parent local key
     */
    public function load(
        iterable $parents,
        RelationMetadata $relation,
        callable $builderFactory,
    ): array {
        $keys = [];
        foreach ($parents as $parent) {
            $key = $this->readKey($parent, $relation->localKey());
            if ($key !== null) {
                $keys[(string) $key] = true;
            }
        }

        if ($keys === []) {
            return [];
        }

        /** @var QueryBuilder $builder */
        $builder = $builderFactory($relation->relatedTable()->name);

        if (! $builder instanceof QueryBuilder) {
            throw new RelationException('The relation builder factory must return a QueryBuilder.');
        }

        $rows = $builder->whereIn($relation->foreignKey(), array_keys($keys))->get();

        $grouped = [];
        foreach ($rows as $row) {
            $foreign = $row[$relation->foreignKey()] ?? null;
            if ($foreign === null) {
                continue;
            }

            $grouped[(string) $foreign][] = $row;
        }

        return $grouped;
    }

    private function readKey(mixed $parent, string $key): mixed
    {
        if (is_array($parent)) {
            return $parent[$key] ?? null;
        }

        if ($parent instanceof EntityInterface) {
            return $parent->getAttribute($key);
        }

        if (is_object($parent)) {
            return $parent->{$key} ?? null;
        }

        return null;
    }
}
