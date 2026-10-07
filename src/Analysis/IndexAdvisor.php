<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use OrmUniversal\Contracts\ValueObjects\TableDefinition;

/**
 * Index recommendations from real schema definitions. Conservative: only
 * foreign keys without a covering prefix and `*_id` columns without an index.
 *
 * @api public
 */
final class IndexAdvisor
{
    /**
     * @return list<array{table: string, column: string, reason: string, suggestion: string}>
     */
    public function adviseTable(string $table, TableDefinition $definition): array
    {
        $advice = [];
        $seen = [];
        $coveredFirst = [];
        $indexed = [];

        foreach ($definition->indexes as $index) {
            $first = strtolower($index->columns[0] ?? '');
            if ($first !== '') {
                $coveredFirst[$first] = true;
            }

            foreach ($index->columns as $column) {
                $indexed[strtolower($column)] = true;
            }
        }

        foreach ($definition->foreignKeys as $foreignKey) {
            foreach ($foreignKey->columns as $column) {
                $key = strtolower($column);
                if (! isset($coveredFirst[$key]) && ! isset($seen[$key])) {
                    $seen[$key] = true;
                    $advice[] = $this->advice($table, $column, 'Foreign key column not covered by an index prefix (constraint '.$foreignKey->name.').');
                }
            }
        }

        foreach ($definition->columns as $column) {
            $name = strtolower($column->name);
            if (str_ends_with($name, '_id') && ! isset($indexed[$name]) && ! isset($seen[$name])) {
                $seen[$name] = true;
                $advice[] = $this->advice($table, $column->name, 'Column looks like a foreign key but has no index.');
            }
        }

        return $advice;
    }

    /**
     * @return array{table: string, column: string, reason: string, suggestion: string}
     */
    private function advice(string $table, string $column, string $reason): array
    {
        return [
            'table' => $table,
            'column' => $column,
            'reason' => $reason,
            'suggestion' => 'CREATE INDEX '.$table.'_'.$column.'_idx ON '.$table.' ('.$column.');',
        ];
    }
}
