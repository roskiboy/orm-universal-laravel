<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

/**
 * Framework model recordset (Eloquent in Laravel). The core never depends on the
 * framework: the factory callable builds the model instance.
 *
 * @api public
 *
 * @template T
 *
 * @extends ArrayRecordset<T>
 */
final class EloquentRecordset extends ArrayRecordset
{
    /**
     * @param  callable(array<string, mixed>): object  $factory
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public static function fromRows(callable $factory, iterable $rows): self
    {
        $models = [];
        foreach ($rows as $row) {
            $models[] = $factory($row);
        }

        return new self($models);
    }
}
