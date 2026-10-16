<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Logical type kinds of the universal type system. Database specific types
 * (jsonb, vector, uniqueidentifier, ...) are expressed as a kind plus parameters.
 *
 * @api public
 */
enum TypeKind: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case BigInteger = 'big_integer';
    case UnsignedInteger = 'unsigned_integer';
    case Decimal = 'decimal';
    case Float = 'float';
    case Boolean = 'boolean';
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'datetime';
    case Timestamp = 'timestamp';
    case Binary = 'binary';
    case Blob = 'blob';
    case Json = 'json';
    case Jsonb = 'jsonb';
    case Uuid = 'uuid';
    case Ulid = 'ulid';
    case Enum = 'enum';
    case Array = 'array';
    case Object = 'object';
    case Vector = 'vector';
    case Geometry = 'geometry';
    case Custom = 'custom';
}
