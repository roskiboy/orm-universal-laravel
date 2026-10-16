<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Sort direction.
 *
 * @api public
 */
enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function sql(): string
    {
        return strtoupper($this->value);
    }
}
