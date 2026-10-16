<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

use OrmUniversal\Relational\Hydration\Hydrator;

/**
 * Entity recordset: hydrates every row into an entity class implementing
 * EntityInterface (or exposing public properties).
 *
 * @api public
 *
 * @template T
 *
 * @extends ArrayRecordset<T>
 */
final class EntityRecordset extends ArrayRecordset
{
    /**
     * @param  class-string  $entityClass
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function fromRows(string $entityClass, iterable $rows): self
    {
        $hydrator = Hydrator::entity($entityClass);

        $entities = [];
        foreach ($rows as $row) {
            $entities[] = $hydrator->hydrate($row);
        }

        return new self($entities);
    }
}
