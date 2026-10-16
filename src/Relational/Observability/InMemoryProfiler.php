<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Observability;

use OrmUniversal\Contracts\ProfilerInterface;
use OrmUniversal\Contracts\ValueObjects\QueryProfileEvent;

/**
 * Bounded in-memory profiler. Providers such as Telescope/OpenTelemetry attach
 * through the ProfilerInterface without becoming mandatory.
 *
 * @api public
 */
final class InMemoryProfiler implements ProfilerInterface
{
    /** @var list<QueryProfileEvent> */
    private array $events = [];

    public function __construct(
        private readonly int $limit = 500,
    ) {}

    public function record(QueryProfileEvent $event): void
    {
        if (count($this->events) >= $this->limit) {
            array_shift($this->events);
        }

        $this->events[] = $event;
    }

    /**
     * @return list<QueryProfileEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->events = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $total = 0.0;
        $slowest = 0.0;
        $byFingerprint = [];

        foreach ($this->events as $event) {
            $total += $event->durationMs;
            $slowest = max($slowest, $event->durationMs);
            $key = (string) $event->fingerprint;
            $byFingerprint[$key] = ($byFingerprint[$key] ?? 0) + 1;
        }

        arsort($byFingerprint);

        return [
            'queries' => count($this->events),
            'total_ms' => round($total, 3),
            'slowest_ms' => round($slowest, 3),
            'repeated' => array_slice($byFingerprint, 0, 10, true),
        ];
    }
}
