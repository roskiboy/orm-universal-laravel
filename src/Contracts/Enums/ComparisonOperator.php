<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Comparison / predicate operators supported by the expression engine.
 *
 * @api public
 */
enum ComparisonOperator: string
{
    case Eq = '=';
    case Neq = '!=';
    case Gt = '>';
    case Gte = '>=';
    case Lt = '<';
    case Lte = '<=';
    case Like = 'like';
    case NotLike = 'not like';
    case ILike = 'ilike';
    case In = 'in';
    case NotIn = 'not in';
    case Between = 'between';
    case NotBetween = 'not between';
    case IsNull = 'is null';
    case IsNotNull = 'is not null';
    case Exists = 'exists';
    case NotExists = 'not exists';
    case Regex = 'regexp';
    case JsonContains = 'json_contains';
    case JsonContainsKey = 'json_contains_key';
    case JsonOverlaps = 'json_overlaps';
    case IsDistinctFrom = 'is distinct from';
    case IsNotDistinctFrom = 'is not distinct from';
    case BinaryEq = '= binary';
    case BinaryNeq = '!= binary';
    case LikeBinary = 'like binary';
    case NotLikeBinary = 'not like binary';
}
