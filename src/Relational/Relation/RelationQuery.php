<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\Exceptions\RelationException;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Execution\QueryExecutor;

/**
 * Builds relation queries as first class query objects:
 *
 * $user->orders() -> paid() -> recent() -> recordset()
 *
 * The relation itself resolves to a QueryBuilder over the related table.
 *
 * @api public
 */
final class RelationQuery
{
    public function __construct(
        private readonly QueryExecutor $executor,
    ) {}

    /**
     * Query the related records for a single parent key value.
     */
    public function for(RelationDefinition|RelationMetadata $relation, mixed $parentKeyValue): QueryBuilder
    {
        $definition = $relation instanceof RelationMetadata ? RelationDefinition::fromMetadata($relation) : $relation;

        if ($definition->type() === RelationDefinition::MORPH_TO) {
            throw new RelationException('MorphTo relations are many-to-one polymorphic: use morphToBatch() with the explicit morph map.');
        }

        if (isset($definition->metadata()['through'])) {
            return $this->throughQuery($definition, [$parentKeyValue]);
        }

        $builder = QueryBuilder::table($this->executor, $definition->relatedTable()->name);

        if ($definition->usesPivot()) {
            return $this->pivotQuery($definition, [$parentKeyValue]);
        }

        $builder->where($definition->foreignKey(), $parentKeyValue);

        if ($definition->morphType() !== null) {
            $builder->where($definition->morphType(), $this->morphClass($definition));
        }

        return $builder;
    }

    /**
     * Batch query for many parent key values (one query, no N+1).
     *
     * @param  list<mixed>  $parentKeyValues
     */
    public function batch(RelationDefinition|RelationMetadata $relation, array $parentKeyValues): QueryBuilder
    {
        $definition = $relation instanceof RelationMetadata ? RelationDefinition::fromMetadata($relation) : $relation;

        if ($parentKeyValues === []) {
            throw new RelationException('Batch relation query requires at least one parent key.');
        }

        if ($definition->type() === RelationDefinition::MORPH_TO) {
            throw new RelationException('MorphTo relations are many-to-one polymorphic: use morphToBatch() with the explicit morph map.');
        }

        if (isset($definition->metadata()['through'])) {
            return $this->throughQuery($definition, $parentKeyValues);
        }

        if ($definition->usesPivot()) {
            return $this->pivotQuery($definition, $parentKeyValues);
        }

        $builder = QueryBuilder::table($this->executor, $definition->relatedTable()->name)
            ->whereIn($definition->foreignKey(), $parentKeyValues);

        if ($definition->morphType() !== null) {
            $builder->where($definition->morphType(), $this->morphClass($definition));
        }

        return $builder;
    }

    /**
     * MorphTo batch: groups parents by `_type` value and runs one query per
     * mapped type. Returns related rows keyed by parent id.
     *
     * @param  list<array<string, mixed>>  $parents
     * @return array<int|string, array<string, mixed>|null>
     */
    public function morphToBatch(RelationDefinition $definition, array $parents): array
    {
        $metadata = $definition->metadata();
        $map = (array) ($metadata['morph_map'] ?? []);

        if ($map === []) {
            throw new RelationException('MorphTo requires an explicit morph map (type => table); none was provided.');
        }

        $typeColumn = (string) ($metadata['morph_type_column'] ?? $definition->morphType() ?? '');
        $idColumn = (string) ($metadata['morph_id_column'] ?? 'morph_id');
        $ownerKey = (string) ($metadata['owner_key'] ?? 'id');

        $grouped = [];
        foreach ($parents as $parent) {
            $type = (string) ($parent[$typeColumn] ?? '');
            $id = $parent[$idColumn] ?? null;

            if ($type === '' || $id === null || ! isset($map[$type])) {
                continue;
            }

            $grouped[$type][(string) $id] = true;
        }

        $result = [];
        foreach ($grouped as $type => $ids) {
            $table = (string) $map[$type];
            $rows = QueryBuilder::table($this->executor, $table)
                ->whereIn($ownerKey, array_keys($ids))
                ->get();

            foreach ($rows as $row) {
                $result[(string) ($row[$ownerKey] ?? '')] = $row;
            }
        }

        return $result;
    }

    /**
     * @param  list<mixed>  $parentKeyValues
     */
    private function throughQuery(RelationDefinition $definition, array $parentKeyValues): QueryBuilder
    {
        $metadata = $definition->metadata();
        $related = $definition->relatedTable()->name;
        $through = (string) $metadata['through'];
        $throughLocalKey = (string) ($metadata['through_local_key'] ?? '');
        $relatedKey = (string) ($metadata['related_key'] ?? 'id');

        if ($throughLocalKey === '') {
            throw new RelationException('Through relation ['.$definition->name().'] is missing through_local_key.');
        }

        $builder = QueryBuilder::table($this->executor, $related)
            ->join($through, $through.'.'.$throughLocalKey, '=', $related.'.'.$relatedKey);

        if (count($parentKeyValues) === 1) {
            $builder->where($through.'.'.$definition->foreignKey(), $parentKeyValues[0]);
        } else {
            $builder->whereIn($through.'.'.$definition->foreignKey(), $parentKeyValues);
        }

        return $builder;
    }

    /**
     * @param  list<mixed>  $parentKeyValues
     */
    private function pivotQuery(RelationDefinition $definition, array $parentKeyValues): QueryBuilder
    {
        $pivot = $definition->pivotTable();
        if ($pivot === null) {
            throw new RelationException('Pivot relation ['.$definition->name().'] is missing the pivot table.');
        }

        $related = $definition->relatedTable();
        $relatedKey = (string) ($definition->metadata()['related_key'] ?? 'id');

        // Join the pivot table to the related table: explicit and dialect portable.
        $builder = QueryBuilder::table($this->executor, $related->name)
            ->select($related->name.'.*')
            ->join($pivot, $pivot.'.'.$relatedKey, '=', $related->name.'.id')
            ->whereIn($pivot.'.'.$definition->foreignKey(), $parentKeyValues);

        if (($definition->metadata()['morph_type'] ?? null) !== null && ($definition->metadata()['morph_class'] ?? null) !== null) {
            $builder->where($pivot.'.'.(string) $definition->metadata()['morph_type'], (string) $definition->metadata()['morph_class']);
        }

        return $builder;
    }

    private function morphClass(RelationDefinition $definition): string
    {
        $class = (string) ($definition->metadata()['model'] ?? '');

        return $class;
    }
}
