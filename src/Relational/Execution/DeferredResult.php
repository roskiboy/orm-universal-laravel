<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Execution;

/**
 * Handle returned by QueryBuilder::defer(). Resolving it flushes the owning
 * batch and returns the shared first row.
 *
 * @api public
 */
final class DeferredResult
{
    /** @var array<string, mixed>|null */
    private ?array $row = null;

    private bool $settled = false;

    public function __construct(
        private readonly DeferredBatch $batch,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(): ?array
    {
        if (! $this->settled) {
            $this->batch->flush();
        }

        return $this->row;
    }

    public function settled(): bool
    {
        return $this->settled;
    }

    /**
     * @param  array<string, mixed>|null  $row
     *
     * @internal
     */
    public function settle(?array $row): void
    {
        $this->row = $row;
        $this->settled = true;
    }
}
