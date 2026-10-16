<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Built-in aggregate functions.
 *
 * @api public
 */
enum AggregateFunction: string
{
    case Count = 'count';
    case CountDistinct = 'count_distinct';
    case Sum = 'sum';
    case Avg = 'avg';
    case Min = 'min';
    case Max = 'max';
    case GroupConcat = 'group_concat';
    case StringAgg = 'string_agg';
    case StdDev = 'stddev';
    case Variance = 'variance';
    case Median = 'median';
    case Percentile = 'percentile';
    case AnyValue = 'any_value';
}
