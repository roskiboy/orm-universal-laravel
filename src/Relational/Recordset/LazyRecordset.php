<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

use OrmUniversal\Contracts\HydratorInterface;

/**
 * Lazy recordset over a chunked source. Memory stays bounded when the dataset is
 * bigger than the process can hold.
 *
 * @api public
 *
 * @template T
 *
 * @extends StreamingRecordset<T>
 */
final class LazyRecordset extends StreamingRecordset
{
    /**
     * @param  callable(int, int): iterable<int, array<string, mixed>>  $source  receives (chunkSize, page)
     */
    public function __construct(callable $source, private readonly int $chunkSize = 1000, ?HydratorInterface $hydrator = null)
    {
        $page = 0;

        parent::__construct(function () use ($source, &$page): iterable {
            $page++;

            return $source($this->chunkSize, $page);
        }, $hydrator);
    }

    public function chunkSize(): int
    {
        return $this->chunkSize;
    }
}
