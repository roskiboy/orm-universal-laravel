<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Execution;

use OrmUniversal\Relational\Events\RetryScheduled;
use OrmUniversal\Relational\Observability\EventEmitter;
use Throwable;

/**
 * Retry policy for transient failures (deadlocks, lock timeouts, serialization
 * failures). It never retries non-idempotent operations.
 *
 * @api public
 */
final class RetryPolicy
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $baseDelayMs = 50,
        private readonly float $multiplier = 2.0,
        private readonly ?EventEmitter $events = null,
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1.');
        }
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation, bool $idempotent = false): mixed
    {
        if (! $idempotent) {
            return $operation();
        }

        $attempt = 0;
        $delay = $this->baseDelayMs;

        while (true) {
            $attempt++;

            try {
                return $operation();
            } catch (Throwable $exception) {
                if ($attempt >= $this->maxAttempts || ! self::isRetryable($exception)) {
                    throw $exception;
                }

                $this->events?->emit(new RetryScheduled($attempt, $delay, $exception->getMessage()));
                usleep($delay * 1000);
                $delay = (int) ($delay * $this->multiplier);
            }
        }
    }

    public static function isRetryable(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach ([
            'deadlock',
            'lock wait timeout',
            'could not serialize access',
            'serialization failure',
            'database is locked',
            'sqlstate[40001]',
            'sqlstate[40p01]',
            'ora-00060',
            'error 1213',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'max_attempts' => $this->maxAttempts,
            'base_delay_ms' => $this->baseDelayMs,
            'multiplier' => $this->multiplier,
        ];
    }
}
