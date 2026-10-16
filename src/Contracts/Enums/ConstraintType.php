<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Table constraints modelled by the schema/migration engine.
 *
 * @api public
 */
enum ConstraintType: string
{
    case Primary = 'primary';
    case Foreign = 'foreign';
    case Unique = 'unique';
    case Check = 'check';
    case Default = 'default';
    case Generated = 'generated';
    case Identity = 'identity';
    case Exclusion = 'exclusion';
}
