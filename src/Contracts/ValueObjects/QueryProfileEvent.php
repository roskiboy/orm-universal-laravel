<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * One profiling record for an executed query. Sensitive bindings are never stored
 * by default; fingerprints are preferred.
 *
 * @api public
 */
final class QueryProfileEvent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $sql,
        public readonly ?QueryFingerprint $fingerprint = null,
        public readonly float $durationMs = 0.0,
        public readonly ?int $rows = null,
        public readonly ?int $memoryBytes = null,
        public readonly ?string $connection = null,
        public readonly ?string $dialect = null,
        public readonly bool $cacheHit = false,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sql' => $this->sql,
            'fingerprint' => (string) $this->fingerprint,
            'duration_ms' => $this->durationMs,
            'rows' => $this->rows,
            'memory_bytes' => $this->memoryBytes,
            'connection' => $this->connection,
            'dialect' => $this->dialect,
            'cache_hit' => $this->cacheHit,
            'context' => $this->context,
        ];
    }
}
