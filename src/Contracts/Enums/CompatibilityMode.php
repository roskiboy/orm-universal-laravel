<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Adoption mode for a Laravel application using the platform.
 *
 * @api public
 */
enum CompatibilityMode: string
{
    /** Existing Laravel behaviour only; platform passively available. */
    case Legacy = 'legacy';
    /** Adapters enabled, results must be byte compatible with Laravel. */
    case Compatibility = 'compatibility';
    /** Eloquent vanilla plus platform queries/recordsets side by side. */
    case Hybrid = 'hybrid';
    /** Platform optimizations enabled with compatibility guarantees. */
    case Optimized = 'optimized';
    /** Platform first; unsupported Laravel-only features reported. */
    case Native = 'native';
    /** Legacy APIs and ambiguous magic raise exceptions. */
    case Strict = 'strict';

    public function allowsLegacyApis(): bool
    {
        return match ($this) {
            self::Legacy, self::Compatibility, self::Hybrid, self::Optimized, self::Native => true,
            self::Strict => false,
        };
    }
}
