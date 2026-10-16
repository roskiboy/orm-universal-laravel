<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

/**
 * Heuristic index advisor over SQL samples captured by the vanilla monitor.
 * Conservative: only proposes indexes for plain WHERE equality/range columns on
 * a single FROM table; qualified columns keep the column name.
 *
 * @api public
 */
final class ProfilerIndexAdvisor
{
    /**
     * @param  list<string>  $samples
     * @return list<array{table: string, column: string, reason: string, suggestion: string}>
     */
    public function suggest(array $samples): array
    {
        $advice = [];
        $seen = [];

        foreach ($samples as $sample) {
            if (! is_string($sample) || trim($sample) === '') {
                continue;
            }

            $table = $this->table($sample);
            if ($table === null) {
                continue;
            }

            foreach ($this->columns($sample) as $column) {
                $key = strtolower($table.'.'.$column);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $advice[] = [
                    'table' => $table,
                    'column' => $column,
                    'reason' => 'WHERE column observed in profiler samples without a known covering index.',
                    'suggestion' => 'CREATE INDEX '.$table.'_'.$column.'_idx ON '.$table.' ('.$column.');',
                ];
            }
        }

        return $advice;
    }

    private function table(string $sql): ?string
    {
        if (preg_match('/\bfrom\s+["`\[]?([A-Za-z_][A-Za-z0-9_]*)["`\]]?/i', $sql, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function columns(string $sql): array
    {
        if (preg_match('/\bwhere\b(.*?)(?:\border\s+by\b|\bgroup\s+by\b|\bhaving\b|\blimit\b|$)/is', $sql, $clause) !== 1) {
            return [];
        }

        preg_match_all(
            '/["`\[]?([A-Za-z_][A-Za-z0-9_]*)["`\]]?\s*(?:=|!=|<>|>=|<=|>|<|like\b|in\s*\()/i',
            $clause[1],
            $matches,
        );

        $columns = [];
        foreach ($matches[1] as $column) {
            $lower = strtolower($column);
            if (in_array($lower, ['and', 'or', 'not', 'null', 'is', 'select', 'where'], true)) {
                continue;
            }
            $columns[$lower] = $column;
        }

        return array_values($columns);
    }
}
