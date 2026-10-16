<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Driver;

use OrmUniversal\Contracts\CursorInterface;
use OrmUniversal\Contracts\ProfilerInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\QueryProfileEvent;
use PDOStatement;

/**
 * Forward-only cursor over a PDO statement. Rows are cached as they are read so
 * multiple iterations do not re-execute the query.
 *
 * @api public
 *
 * @implements CursorInterface<array<string, mixed>>
 */
final class PdoCursor implements CursorInterface
{
    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private bool $exhausted = false;

    private float $started;

    public function __construct(
        private PDOStatement $statement,
        private readonly CompiledQuery $query,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $connection = null,
        private readonly ?string $dialect = null,
    ) {
        $this->started = microtime(true);
    }

    public function getIterator(): \Traversable
    {
        $index = 0;

        while (true) {
            if (isset($this->rows[$index])) {
                yield $index => $this->rows[$index];
                $index++;

                continue;
            }

            if ($this->exhausted) {
                return;
            }

            $row = $this->statement->fetch();

            if ($row === false) {
                $this->exhausted = true;
                $this->finish();

                return;
            }

            $this->rows[$index] = $row;
            yield $index => $row;
            $index++;
        }
    }

    public function count(): int
    {
        foreach ($this->getIterator() as $ignored) {
            // Iterate to exhaustion so the row buffer is complete.
        }

        return count($this->rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        foreach ($this->getIterator() as $ignored) {
            // Drain.
        }

        return $this->rows;
    }

    public function close(): void
    {
        $this->statement->closeCursor();
        $this->exhausted = true;
        $this->finish();
    }

    private function finish(): void
    {
        if ($this->profiler === null) {
            return;
        }

        $this->profiler->record(new QueryProfileEvent(
            sql: $this->query->sql,
            fingerprint: $this->query->fingerprint(),
            durationMs: round((microtime(true) - $this->started) * 1000, 3),
            rows: count($this->rows),
            memoryBytes: memory_get_usage(),
            connection: $this->connection,
            dialect: $this->dialect,
            context: ['mode' => 'cursor'],
        ));
    }
}
