<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Performance preset. Extreme mode may reduce optional instrumentation but never
 * disables security, bindings or transactional correctness.
 *
 * @api public
 */
enum PerformanceMode: string
{
    case Safe = 'safe';
    case Balanced = 'balanced';
    case Fast = 'fast';
    case Extreme = 'extreme';

    public function profilingEnabled(): bool
    {
        return $this !== self::Extreme;
    }

    public function metadataChecksEnabled(): bool
    {
        return $this === self::Safe || $this === self::Balanced;
    }
}
