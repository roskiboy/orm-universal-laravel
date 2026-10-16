<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Events;

/**
 * Emitted when the N+1 detector observes repeated relation loads.
 *
 * @api public
 */
final class N1Detected
{
    public function __construct(
        public readonly string $relation,
        public readonly int $loads,
        public readonly int $threshold,
    ) {}
}
