<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Concrete recordset implementations.
 *
 * @api public
 */
enum RecordsetMode: string
{
    case Array = 'array';
    case Object = 'object';
    case Entity = 'entity';
    case Eloquent = 'eloquent';
    case Streaming = 'streaming';
    case Lazy = 'lazy';
    case Cursor = 'cursor';
}
