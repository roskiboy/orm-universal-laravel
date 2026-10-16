<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Lifecycle of a transaction on a connection.
 *
 * @api public
 */
enum TransactionState: string
{
    case None = 'none';
    case Active = 'active';
    case Committed = 'committed';
    case RolledBack = 'rolled_back';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return in_array($this, [self::Committed, self::RolledBack, self::Failed], true);
    }
}
