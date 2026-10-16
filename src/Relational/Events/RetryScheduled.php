<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Events;

/**
 * Emitted when the retry policy schedules another attempt.
 *
 * @api public
 */
final class RetryScheduled
{
    public function __construct(
        public readonly int $attempt,
        public readonly int $delayMs,
        public readonly string $reason,
    ) {}
}
