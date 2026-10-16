<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Support status for a backend/version in the compatibility matrix.
 *
 * @api public
 */
enum StabilityLevel: string
{
    case Stable = 'stable';
    case Supported = 'supported';
    case Partial = 'partial';
    case Experimental = 'experimental';
    case Planned = 'planned';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
