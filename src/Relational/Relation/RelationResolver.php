<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

/**
 * Infers relation metadata from foreign keys. It never guesses: a relation is
 * only produced when the evidence (a matching FK) is unambiguous.
 *
 * @api public
 */
final class RelationResolver
{
    /**
     * @param  list<ForeignKeyDefinition>  $foreignKeys  of the inspected table
     * @return list<RelationMetadata>
     */
    public function belongsToMany(string $localTable, array $foreignKeys): array
    {
        $relations = [];

        foreach ($foreignKeys as $key) {
            $column = $key->columns[0] ?? null;
            if ($column === null || count($key->columns) !== 1) {
                continue;
            }

            $name = str_ends_with($column, '_id') ? substr($column, 0, -3) : $column;

            $relations[] = new RelationMetadata(
                name: $name,
                type: 'belongsTo',
                relatedTable: TableIdentifier::fromString($key->foreignTable),
                foreignKey: $column,
                localKey: 'id',
                toMany: false,
                metadata: ['inferred_from' => 'foreign_key', 'constraint' => $key->name, 'local_table' => $localTable],
            );
        }

        return $relations;
    }

    /**
     * @param  list<ForeignKeyDefinition>  $foreignKeys  of other tables referencing $localTable
     * @return list<RelationMetadata>
     */
    public function hasMany(string $localTable, string $referencingTable, array $foreignKeys): array
    {
        $relations = [];

        foreach ($foreignKeys as $key) {
            if (strcasecmp($key->foreignTable, $localTable) !== 0) {
                continue;
            }

            $column = $key->columns[0] ?? null;
            if ($column === null) {
                continue;
            }

            $relations[] = new RelationMetadata(
                name: $referencingTable,
                type: 'hasMany',
                relatedTable: TableIdentifier::fromString($referencingTable),
                foreignKey: $column,
                localKey: $key->foreignColumns[0] ?? 'id',
                toMany: true,
                metadata: ['inferred_from' => 'foreign_key', 'constraint' => $key->name],
            );
        }

        return $relations;
    }
}
