<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Execution;

use OrmUniversal\Relational\Ast\SelectQuery;

/**
 * Per-request batch of deferred single-row reads. Duplicate query shapes (same
 * AST fingerprint and parameters) are resolved once and shared by every handle.
 *
 * @api public
 */
final class DeferredBatch
{
    /**
     * @var array<string, array{query: SelectQuery, parameters: array<string, mixed>, results: list<DeferredResult>}>
     */
    private array $pending = [];

    private int $unique = 0;

    public function __construct(
        private readonly QueryExecutor $executor,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function defer(SelectQuery $query, array $parameters = []): DeferredResult
    {
        $result = new DeferredResult($this);
        $key = $this->key($query, $parameters);

        $this->pending[$key] ??= ['query' => $query, 'parameters' => $parameters, 'results' => []];
        $this->pending[$key]['results'][] = $result;

        return $result;
    }

    public function pending(): int
    {
        return count($this->pending);
    }

    public function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        $batch = $this->pending;
        $this->pending = [];

        foreach ($batch as $entry) {
            $rows = $this->executor->rows($entry['query'], $entry['parameters']);
            $row = $rows[0] ?? null;

            foreach ($entry['results'] as $result) {
                $result->settle($row);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function key(SelectQuery $query, array $parameters): string
    {
        try {
            $compiled = $this->executor->compile($query);

            return hash('xxh128', $compiled->sql.'|'.serialize($compiled->bindings).'|'.serialize($parameters));
        } catch (\Throwable) {
            return 'unique:'.++$this->unique;
        }
    }
}
