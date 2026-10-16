<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

use OrmUniversal\Contracts\CursorInterface;
use OrmUniversal\Contracts\HydratorInterface;

/**
 * Cursor recordset: wraps a driver cursor and hydrates rows on demand without
 * buffering the whole result set.
 *
 * @api public
 *
 * @template T
 *
 * @extends StreamingRecordset<T>
 */
final class CursorRecordset extends StreamingRecordset
{
    /**
     * @param  CursorInterface<array<string, mixed>>  $cursor
     */
    public function __construct(private readonly CursorInterface $cursor, ?HydratorInterface $hydrator = null)
    {
        parent::__construct(fn (): iterable => $cursor->getIterator(), $hydrator);
    }

    public function cursorSource(): CursorInterface
    {
        return $this->cursor;
    }
}
