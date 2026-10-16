<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * High level family of a persistence backend.
 *
 * @api public
 */
enum DatabaseFamily: string
{
    case Relational = 'relational';
    case Document = 'document';
    case KeyValue = 'key_value';
    case Search = 'search';
    case Graph = 'graph';
    case WideColumn = 'wide_column';
    case TimeSeries = 'time_series';
}
