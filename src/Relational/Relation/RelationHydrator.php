<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\EntityInterface;

/**
 * Attaches batched relation results to parents without instantiating extra
 * objects. Parents may be arrays, entities or plain objects.
 *
 * @api public
 */
final class RelationHydrator
{
    /**
     * @param  iterable<int, mixed>  $parents
     * @param  array<int|string, list<array<string, mixed>>>  $grouped  keyed by parent local key
     * @return list<mixed> parents with the relation attached
     */
    public function hydrate(iterable $parents, RelationMetadata $relation, array $grouped): array
    {
        $result = [];

        foreach ($parents as $parent) {
            $key = (string) $this->readKey($parent, $relation->localKey());
            $rows = $grouped[$key] ?? [];

            $result[] = $this->attach($parent, $relation, $rows);
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function attach(mixed $parent, RelationMetadata $relation, array $rows): mixed
    {
        $value = $relation->isToMany()
            ? new RelationCollection($relation->name(), $rows)
            : ($rows[0] ?? null);

        if (is_array($parent)) {
            $parent[$relation->name()] = $value;

            return $parent;
        }

        if ($parent instanceof EntityInterface) {
            $parent->setAttribute($relation->name(), $value);

            return $parent;
        }

        if (is_object($parent)) {
            $parent->{$relation->name()} = $value;
        }

        return $parent;
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
