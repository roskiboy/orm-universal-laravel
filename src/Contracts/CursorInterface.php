<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Lazy iteration over result rows. Implementations may be backed by PDO fetch,
 * buffered arrays or any streaming source.
 *
 * @api public
 *
 * @template T
 *
 * @extends \IteratorAggregate<int, T>
 */
interface CursorInterface extends \Countable, \IteratorAggregate
{
    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable;

    public function close(): void;
}
