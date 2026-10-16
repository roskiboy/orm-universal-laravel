<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Events;

/**
 * Emitted after every executed statement. Bindings are never included.
 *
 * @api public
 */
final class QueryExecuted
{
    public function __construct(
        public readonly string $sql,
        public readonly int $bindingCount,
        public readonly float $durationMs,
        public readonly ?string $connection = null,
        public readonly ?string $dialect = null,
    ) {}
}
