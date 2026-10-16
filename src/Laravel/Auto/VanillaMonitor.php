<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Auto;

use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;

/**
 * Zero-risk monitor: listens to Laravel query events and reports repeated
 * fingerprints (N+1 / duplicate storms), slow queries and eager-load
 * suggestions. It never changes query behavior.
 *
 * @api public
 */
final class VanillaMonitor
{
    /** @var list<array{sql: string, ms: float, connection: string, fingerprint: string}> */
    private array $events = [];

    /** @var array<string, array{count: int, sample: string}> */
    private array $fingerprints = [];

    /** @var list<array{sql: string, ms: float, connection: string}> */
    private array $slow = [];

    public function __construct(
        private readonly int $slowMs = 200,
        private readonly int $n1Threshold = 3,
    ) {}

    public function record(object $event): void
    {
        $sql = (string) ($event->sql ?? '');

        if ($sql === '') {
            return;
        }

        $ms = (float) ($event->time ?? 0);
        $connection = (string) ($event->connectionName ?? 'default');
        $normalized = QueryFingerprint::normalize($sql);

        $this->events[] = ['sql' => $sql, 'ms' => $ms, 'connection' => $connection, 'fingerprint' => sha1($normalized)];
        $this->fingerprints[$normalized] = [
            'count' => ($this->fingerprints[$normalized]['count'] ?? 0) + 1,
            'sample' => $sql,
        ];

        if ($ms >= $this->slowMs) {
            $this->slow[] = ['sql' => $sql, 'ms' => $ms, 'connection' => $connection];
        }
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return array<string, array{count: int, sample: string}>
     */
    public function duplicates(): array
    {
        return array_filter(
            $this->fingerprints,
            fn (array $entry): bool => $entry['count'] >= $this->n1Threshold,
        );
    }

    /**
     * @return list<array{sql: string, ms: float, connection: string}>
     */
    public function slowQueries(): array
    {
        return $this->slow;
    }

    /**
     * @return list<string>
     */
    public function suggestions(): array
    {
        $suggestions = [];

        foreach ($this->duplicates() as $entry) {
            $suggestions[] = sprintf(
                'Query repeated %d times: consider eager loading or batching. Fingerprint SQL: %s',
                $entry['count'],
                substr($entry['sample'], 0, 140),
            );
        }

        foreach ($this->slow as $entry) {
            $suggestions[] = sprintf(
                'Slow query (%.1f ms) on [%s]: review indexes. SQL: %s',
                $entry['ms'],
                $entry['connection'],
                substr($entry['sql'], 0, 140),
            );
        }

        return $suggestions;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'mode' => 'monitor',
            'queries' => $this->count(),
            'unique_fingerprints' => count($this->fingerprints),
            'duplicates' => array_map(static fn (array $entry): array => ['count' => $entry['count'], 'sample' => $entry['sample']], $this->duplicates()),
            'slow_queries' => $this->slow,
            'suggestions' => $this->suggestions(),
            'thresholds' => ['slow_ms' => $this->slowMs, 'n_plus_one' => $this->n1Threshold],
        ];
    }

    public function reset(): void
    {
        $this->events = [];
        $this->fingerprints = [];
        $this->slow = [];
    }
}
