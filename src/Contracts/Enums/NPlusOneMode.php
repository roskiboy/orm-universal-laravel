<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Reaction when the N+1 detector observes repeated relation loads.
 *
 * @api public
 */
enum NPlusOneMode: string
{
    case Ignore = 'ignore';
    case Warning = 'warning';
    case Log = 'log';
    case Exception = 'exception';
    case Telemetry = 'telemetry';
}
