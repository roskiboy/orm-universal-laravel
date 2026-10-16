<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Recordset;

use OrmUniversal\Contracts\HydratorInterface;

/**
 * Streaming recordset over a row source. Iteration stays lazy while the source is
 * being consumed; any operation that needs the whole set buffers rows as they are
 * read so the source is never re-executed.
 *
 * @api public
 *
 * @template T
 *
 * @extends ArrayRecordset<T>
 */
class StreamingRecordset extends ArrayRecordset
{
    /** @var (callable(): iterable<int, array<string, mixed>>)|null */
    private $source;

    private readonly ?HydratorInterface $hydrator;

    private bool $sourceExhausted = false;

    /**
     * @param  callable(): iterable<int, array<string, mixed>>  $source
     */
    public function __construct(callable $source, ?HydratorInterface $hydrator = null)
    {
        parent::__construct([]);

        $this->source = $source;
        $this->hydrator = $hydrator;
    }

    /**
     * @return list<T>
     */
    protected function items(): array
    {
        if (! $this->sourceExhausted) {
            foreach ($this->getIterator() as $ignored) {
                // Drain the source into the buffer.
            }
        }

        return $this->items;
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        $index = 0;

        while (true) {
            if (array_key_exists($index, $this->items)) {
                yield $index => $this->items[$index];
                $index++;

                continue;
            }

            if ($this->sourceExhausted || $this->source === null) {
                return;
            }

            foreach (($this->source)() as $row) {
                $item = $this->hydrator !== null ? $this->hydrator->hydrate($row) : $row;
                $this->items[$index] = $item;
                yield $index => $item;
                $index++;
            }

            $this->sourceExhausted = true;
            $this->source = null;

            return;
        }
    }
}
