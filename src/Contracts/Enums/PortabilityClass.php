<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Portability classification of a query or single AST node.
 *
 * @api public
 */
enum PortabilityClass: string
{
    case Portable = 'portable';
    case PartiallyPortable = 'partially_portable';
    case DialectSpecific = 'dialect_specific';

    public function weight(): float
    {
        return match ($this) {
            self::Portable => 1.0,
            self::PartiallyPortable => 0.5,
            self::DialectSpecific => 0.0,
        };
    }
}
