<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

/**
 * How a dialect expresses INSERT ... ON CONFLICT / MERGE / REPLACE.
 *
 * @api public
 */
enum UpsertStyle: string
{
    case OnDuplicateKey = 'on_duplicate_key';
    case OnConflict = 'on_conflict';
    case Merge = 'merge';
    case Replace = 'replace';
}
