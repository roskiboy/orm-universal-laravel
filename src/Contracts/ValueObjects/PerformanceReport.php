<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Static performance assessment of a query before execution.
 *
 * @api public
 */
final class PerformanceReport
{
    /**
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $estimatedComplexity = 'low',
        public readonly string $hydrationCost = 'standard',
        public readonly bool $streamingAvailable = true,
        public readonly bool $potentialNPlusOne = false,
        public readonly array $notes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'estimated_complexity' => $this->estimatedComplexity,
            'hydration_cost' => $this->hydrationCost,
            'streaming_available' => $this->streamingAvailable,
            'potential_n_plus_one' => $this->potentialNPlusOne,
            'notes' => $this->notes,
        ];
    }
}
