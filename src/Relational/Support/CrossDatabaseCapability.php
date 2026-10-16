<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Support;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;

/**
 * Cross-database / cross-connection capability guard. The platform never assumes
 * that joins across connections or engines are portable.
 *
 * @api public
 */
final class CrossDatabaseCapability
{
    public function supportsCrossDatabaseJoin(): bool
    {
        return false;
    }

    public function supportsCrossConnectionJoin(): bool
    {
        return false;
    }

    public function assertJoinAllowed(?string $connectionA, ?string $connectionB): void
    {
        $left = $connectionA ?? 'default';
        $right = $connectionB ?? 'default';

        if ($left === $right) {
            return;
        }

        throw new UnsupportedFeatureException(
            feature: 'Cross-connection join',
            engine: $left.' -> '.$right,
            engineVersion: 'n/a',
            reason: 'Joins are only portable inside a single connection/database',
            alternatives: [
                'Run two queries and join in the application layer',
                'Copy the data into the same connection (ETL/materialized view)',
                'Use a federated query capability of the engine when documented',
            ],
        );
    }

    /**
     * @return array<string, bool>
     */
    public function report(): array
    {
        return [
            'cross_database_join' => $this->supportsCrossDatabaseJoin(),
            'cross_connection_join' => $this->supportsCrossConnectionJoin(),
        ];
    }
}
