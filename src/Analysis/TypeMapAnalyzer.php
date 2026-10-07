<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;

/**
 * Compares real database columns with the model `$casts` and produces
 * suggestions. Decimals are never suggested as float.
 *
 * @api public
 */
final class TypeMapAnalyzer
{
    /**
     * @param  list<ColumnDefinition>  $columns
     * @param  array<string, mixed>  $casts
     * @return list<array{column: string, database_type: string, current: mixed, suggested: string, severity: string, reason: string}>
     */
    public function analyze(array $columns, array $casts): array
    {
        $suggestions = [];

        foreach ($columns as $column) {
            $expected = $this->expectedCast($column);

            if ($expected === null) {
                continue;
            }

            $current = $casts[$column->name] ?? null;

            if ($current === null) {
                $suggestions[] = $this->suggestion($column, null, $expected, 'missing', 'Column type implies a cast that is not declared.');

                continue;
            }

            if ($this->matches($expected, $current)) {
                continue;
            }

            $decimalAsNumber = str_starts_with($expected, 'decimal')
                && is_string($current)
                && in_array(strtolower($current), ['float', 'double', 'real', 'int', 'integer'], true);

            $suggestions[] = $this->suggestion(
                $column,
                $current,
                $expected,
                $decimalAsNumber ? 'error' : 'warning',
                $decimalAsNumber
                    ? 'Decimal cast as float/integer loses precision; decimals must stay strings.'
                    : 'Declared cast does not match the database column type.',
            );
        }

        return $suggestions;
    }

    private function expectedCast(ColumnDefinition $column): ?string
    {
        $type = strtolower($column->databaseType);

        if (str_starts_with($type, 'decimal') || str_starts_with($type, 'numeric')) {
            return $column->scale !== null ? 'decimal:'.$column->scale : 'decimal';
        }

        return match (true) {
            str_starts_with($type, 'json') => 'array',
            str_starts_with($type, 'bool') => 'bool',
            str_starts_with($type, 'tinyint') && $column->length === 1 => 'bool',
            $type === 'date' => 'date',
            str_starts_with($type, 'datetime'), str_starts_with($type, 'timestamp') => 'datetime',
            $type === 'uuid' => 'uuid',
            str_starts_with($type, 'vector') => 'vector',
            default => null,
        };
    }

    private function matches(string $expected, mixed $current): bool
    {
        if (! is_string($current)) {
            return false;
        }

        $current = strtolower($current);

        return match (true) {
            str_starts_with($expected, 'decimal') => str_starts_with($current, 'decimal') || str_starts_with($current, 'numeric'),
            $expected === 'array' => in_array($current, ['array', 'json', 'collection'], true),
            $expected === 'bool' => in_array($current, ['bool', 'boolean'], true),
            $expected === 'date' => in_array($current, ['date', 'immutable_date'], true),
            $expected === 'datetime' => in_array($current, ['datetime', 'immutable_datetime', 'timestamp'], true),
            default => $current === $expected,
        };
    }

    /**
     * @return array{column: string, database_type: string, current: mixed, suggested: string, severity: string, reason: string}
     */
    private function suggestion(ColumnDefinition $column, mixed $current, string $suggested, string $severity, string $reason): array
    {
        return [
            'column' => $column->name,
            'database_type' => $column->databaseType,
            'current' => $current,
            'suggested' => $suggested,
            'severity' => $severity,
            'reason' => $reason,
        ];
    }
}
