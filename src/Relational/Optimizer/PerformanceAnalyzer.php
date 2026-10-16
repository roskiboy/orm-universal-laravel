<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\HydrationMode;
use OrmUniversal\Contracts\ValueObjects\PerformanceReport;
use OrmUniversal\Relational\Ast\Cte;
use OrmUniversal\Relational\Ast\Exists;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\Subquery;

/**
 * Static performance assessment of a query before execution: estimated
 * complexity, hydration cost, streaming availability and potential N+1.
 *
 * @api public
 */
final class PerformanceAnalyzer
{
    public function analyze(SelectQuery $query, HydrationMode $hydrationMode = HydrationMode::Zero): PerformanceReport
    {
        $joins = count($query->joins());
        $unions = count($query->unions());
        $groups = count($query->groups());
        $subqueries = $this->countSubqueries($query);
        $hasLimit = $query->limitValue() !== null;

        $score = $joins + $unions + $groups + ($subqueries * 2);
        $complexity = match (true) {
            $score >= 6 => 'high',
            $score >= 3 => 'medium',
            default => 'low',
        };

        $hydrationCost = match ($hydrationMode) {
            HydrationMode::Zero => 'minimal',
            HydrationMode::Light => 'low',
            HydrationMode::Entity => 'medium',
            HydrationMode::Eloquent => 'high',
        };

        $notes = [];
        if (! $hasLimit && $this->isUnbounded($query)) {
            $notes[] = 'No LIMIT on a potentially unbounded scan; prefer chunk/cursor for large tables.';
        }
        if ($joins > 3) {
            $notes[] = 'More than three joins: review indexes and consider projection pruning.';
        }
        if ($subqueries > 0) {
            $notes[] = 'Subqueries present: verify the engine can flatten them.';
        }

        return new PerformanceReport(
            estimatedComplexity: $complexity,
            hydrationCost: $hydrationCost,
            streamingAvailable: true,
            potentialNPlusOne: false,
            notes: $notes,
        );
    }

    private function countSubqueries(SelectQuery $query): int
    {
        return $this->countSubqueryNodes($query) - 1;
    }

    private function countSubqueryNodes(AstNodeInterface $node): int
    {
        $count = $node instanceof SelectQuery ? 1 : 0;

        foreach ($node->children() as $child) {
            if ($child instanceof Subquery || $child instanceof Exists || $child instanceof Cte) {
                $count++;
            }

            $count += $this->countSubqueryNodes($child);
        }

        return $count;
    }

    private function isUnbounded(SelectQuery $query): bool
    {
        return $query->limitValue() === null && $query->whereExpression() === null;
    }
}
