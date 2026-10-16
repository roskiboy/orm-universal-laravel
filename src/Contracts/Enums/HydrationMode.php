<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * How raw database rows are converted into PHP values.
 *
 * @api public
 */
enum HydrationMode: string
{
    /** Raw associative arrays, zero object allocation. */
    case Zero = 'zero';
    /** Light stdClass objects. */
    case Light = 'light';
    /** Entity objects implementing EntityInterface. */
    case Entity = 'entity';
    /** Framework models (Eloquent in Laravel). */
    case Eloquent = 'eloquent';
}
