<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

/**
 * Light object recordset: every record is a stdClass. Convenient for views while
 * staying allocation-cheap.
 *
 * @api public
 *
 * @template T
 *
 * @extends ArrayRecordset<T>
 */
final class ObjectRecordset extends ArrayRecordset
{
    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function fromRows(iterable $rows): self
    {
        $objects = [];
        foreach ($rows as $row) {
            $objects[] = (object) $row;
        }

        return new self($objects);
    }

    public static function fromArray(array $items): self
    {
        return new self($items);
    }
}
