<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Execution;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Batch planner: splits bulk operations according to the dialect placeholder and
 * packet limits so large writes never exceed engine limits.
 *
 * @api public
 */
final class BatchPlanner
{
    public function __construct(
        private readonly AbstractDialect $dialect,
        private readonly float $safetyFactor = 0.5,
    ) {}

    public function maxParameters(): int
    {
        return (int) $this->dialect->capabilities()->value(Capability::MAX_PLACEHOLDERS, 1000);
    }

    public function maxRowsPerBatch(int $columnsPerRow): int
    {
        $columnsPerRow = max(1, $columnsPerRow);
        $budget = max(1, (int) floor($this->maxParameters() * $this->safetyFactor));

        return max(1, (int) floor($budget / $columnsPerRow));
    }

    /**
     * Adaptive power-of-two batch size: the largest power of two not exceeding
     * the placeholder budget. Uniform chunks keep the prepared-statement cache
     * hot and reduce packet-size variance on heterogeneous rows.
     */
    public function maxRowsPerBatchAdaptive(int $columnsPerRow): int
    {
        $max = $this->maxRowsPerBatch($columnsPerRow);
        $power = 1;

        while (($power * 2) <= $max) {
            $power *= 2;
        }

        return $power;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array<string, mixed>>>
     */
    public function chunkRowsAdaptive(array $rows, int $columnsPerRow): array
    {
        if ($rows === []) {
            return [];
        }

        return array_chunk($rows, $this->maxRowsPerBatchAdaptive($columnsPerRow));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array<string, mixed>>>
     */
    public function chunkRows(array $rows, int $columnsPerRow): array
    {
        if ($rows === []) {
            return [];
        }

        return array_chunk($rows, $this->maxRowsPerBatch($columnsPerRow));
    }

    /**
     * @param  list<mixed>  $values
     * @return list<list<mixed>>
     */
    public function chunkValues(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $budget = max(1, (int) floor($this->maxParameters() * $this->safetyFactor));

        return array_chunk($values, $budget);
    }

    /**
     * @return array{dialect: string, version: string, max_parameters: int, safety_factor: float}
     */
    public function report(): array
    {
        return [
            'dialect' => $this->dialect->vendor()->value,
            'version' => $this->dialect->version()->raw,
            'max_parameters' => $this->maxParameters(),
            'safety_factor' => $this->safetyFactor,
        ];
    }
}
