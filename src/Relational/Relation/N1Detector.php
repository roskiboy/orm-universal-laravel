<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\Enums\NPlusOneMode;
use OrmUniversal\Contracts\Exceptions\RelationException;
use OrmUniversal\Relational\Events\N1Detected;
use OrmUniversal\Relational\Observability\EventEmitter;
use Psr\Log\LoggerInterface;

/**
 * N+1 detector. It observes repeated relation loads for the same set of parents
 * and reacts according to the configured mode.
 *
 * @api public
 */
final class N1Detector
{
    /** @var array<string, int> */
    private array $loads = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly NPlusOneMode $mode = NPlusOneMode::Warning,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $threshold = 2,
        private readonly ?EventEmitter $events = null,
    ) {}

    public function record(string $relation, int $parentCount = 1): void
    {
        if ($parentCount <= 1) {
            return;
        }

        $this->loads[$relation] = ($this->loads[$relation] ?? 0) + 1;

        if ($this->loads[$relation] >= $this->threshold) {
            $this->react($relation);
        }
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function reset(): void
    {
        $this->loads = [];
        $this->warnings = [];
    }

    /**
     * @return array<string, int>
     */
    public function loads(): array
    {
        return $this->loads;
    }

    private function react(string $relation): void
    {
        $message = 'Potential N+1 detected: relation ['.$relation.'] loaded '.$this->loads[$relation].' times with multiple parents.';

        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }

        $this->events?->emit(new N1Detected($relation, $this->loads[$relation], $this->threshold));

        match ($this->mode) {
            NPlusOneMode::Ignore => null,
            NPlusOneMode::Warning => trigger_error($message, E_USER_WARNING),
            NPlusOneMode::Log => $this->logger?->warning($message),
            NPlusOneMode::Telemetry => $this->logger?->info('[ude.n+1] '.$message),
            NPlusOneMode::Exception => throw new RelationException($message),
        };
    }
}
