<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Schema;

use OrmUniversal\Contracts\ValueObjects\SchemaSnapshot;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;

/**
 * Compares two schema snapshots and reports differences without touching the
 * database. Never executes anything.
 *
 * @api public
 */
final class SchemaDiffer
{
    /**
     * @return array<string, mixed>
     */
    public function diff(SchemaSnapshot $current, SchemaSnapshot $desired): array
    {
        $changes = [
            'tables_to_create' => [],
            'tables_to_drop' => [],
            'tables_changed' => [],
        ];

        foreach ($desired->tables as $name => $desiredTable) {
            $currentTable = $current->table($name);

            if ($currentTable === null) {
                $changes['tables_to_create'][] = $name;

                continue;
            }

            $tableChanges = $this->diffTable($currentTable, $desiredTable);
            if ($tableChanges !== ['columns_to_add' => [], 'columns_to_drop' => [], 'columns_changed' => [], 'indexes_to_add' => [], 'foreign_keys_to_add' => []]) {
                $changes['tables_changed'][$name] = $tableChanges;
            }
        }

        foreach ($current->tables as $name => $ignored) {
            if (! isset($desired->tables[$name])) {
                $changes['tables_to_drop'][] = $name;
            }
        }

        return $changes;
    }

    /**
     * @return array<string, list<string>>
     */
    public function diffTable(TableDefinition $current, TableDefinition $desired): array
    {
        $changes = [
            'columns_to_add' => [],
            'columns_to_drop' => [],
            'columns_changed' => [],
            'indexes_to_add' => [],
            'foreign_keys_to_add' => [],
        ];

        foreach ($desired->columns as $column) {
            $existing = $current->column($column->name);

            if ($existing === null) {
                $changes['columns_to_add'][] = $column->name;

                continue;
            }

            if (strcasecmp($existing->databaseType, $column->databaseType) !== 0
                || $existing->nullable !== $column->nullable
            ) {
                $changes['columns_changed'][] = $column->name;
            }
        }

        $desiredColumns = array_map(static fn ($column): string => strtolower($column->name), $desired->columns);
        foreach ($current->columns as $column) {
            if (! in_array(strtolower($column->name), $desiredColumns, true)) {
                $changes['columns_to_drop'][] = $column->name;
            }
        }

        $currentIndexes = array_map(static fn ($index): string => strtolower($index->name), $current->indexes);
        foreach ($desired->indexes as $index) {
            if (! in_array(strtolower($index->name), $currentIndexes, true)) {
                $changes['indexes_to_add'][] = $index->name;
            }
        }

        $currentForeignKeys = array_map(static fn ($key): string => strtolower($key->name), $current->foreignKeys);
        foreach ($desired->foreignKeys as $key) {
            if (! in_array(strtolower($key->name), $currentForeignKeys, true)) {
                $changes['foreign_keys_to_add'][] = $key->name;
            }
        }

        return $changes;
    }
}
