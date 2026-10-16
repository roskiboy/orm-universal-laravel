<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Chooses the loading strategy for a relation without changing semantics:
 * join, batch IN, chunked IN or subquery.
 *
 * @api public
 */
final class RelationOptimizer
{
    /**
     * @return array{strategy: string, reason: string, batch_size: int|null}
     */
    public function plan(
        RelationMetadata $relation,
        AbstractDialect $dialect,
        int $parentCount = 1,
        bool $parentHasWhere = false,
        bool $needsRelatedFilter = false,
    ): array {
        if ($parentCount <= 1) {
            return [
                'strategy' => 'single_query',
                'reason' => 'One parent: a single related query is optimal.',
                'batch_size' => null,
            ];
        }

        if (! $relation->isToMany() && ! $needsRelatedFilter) {
            return [
                'strategy' => 'join',
                'reason' => 'To-one relation without filters: a JOIN avoids a second round trip.',
                'batch_size' => null,
            ];
        }

        if ($needsRelatedFilter && $parentHasWhere) {
            return [
                'strategy' => 'subquery',
                'reason' => 'Filtered to-many relation over filtered parents: an IN subquery keeps one statement portable.',
                'batch_size' => null,
            ];
        }

        $maxPlaceholders = (int) $dialect->capabilities()->value(Capability::MAX_PLACEHOLDERS, 1000);
        $batchSize = max(1, (int) floor($maxPlaceholders / 2));

        if ($parentCount <= $batchSize) {
            return [
                'strategy' => 'batch_in',
                'reason' => 'To-many relation: one batched IN query prevents N+1.',
                'batch_size' => $parentCount,
            ];
        }

        return [
            'strategy' => 'chunked_in',
            'reason' => 'Parent set exceeds the placeholder budget: chunked IN batches.',
            'batch_size' => $batchSize,
        ];
    }
}
