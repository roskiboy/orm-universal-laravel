<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Events;

use OrmUniversal\Contracts\Enums\TransactionState;

/**
 * Emitted when a transaction reaches a final state (committed / rolled back).
 *
 * @api public
 */
final class TransactionLifecycle
{
    public function __construct(
        public readonly TransactionState $state,
        public readonly ?string $connection = null,
        public readonly int $level = 0,
    ) {}
}
