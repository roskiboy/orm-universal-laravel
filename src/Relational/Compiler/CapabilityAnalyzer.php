<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Compiler;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Cte;
use OrmUniversal\Relational\Ast\DateExpression;
use OrmUniversal\Relational\Ast\DeleteQuery;
use OrmUniversal\Relational\Ast\Exists;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\Join;
use OrmUniversal\Relational\Ast\JsonExpression;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\UpdateQuery;
use OrmUniversal\Relational\Ast\VectorDistance;
use OrmUniversal\Relational\Ast\WindowExpression;

/**
 * Static capability analysis: walks an AST and reports which capabilities the
 * target dialect must provide. Negotiation happens before compilation.
 *
 * @api public
 */
final class CapabilityAnalyzer
{
    /**
     * @return list<string>
     */
    public static function forNode(AstNodeInterface $node): array
    {
        $found = [];
        self::walk($node, $found);

        return array_keys($found);
    }

    /**
     * @param  array<string, true>  $found
     */
    private static function walk(AstNodeInterface $node, array &$found): void
    {
        self::inspect($node, $found);

        foreach ($node->children() as $child) {
            self::walk($child, $found);
        }
    }

    /**
     * @param  array<string, true>  $found
     */
    private static function inspect(AstNodeInterface $node, array &$found): void
    {
        if ($node instanceof WindowExpression) {
            $found[Capability::WINDOW_FUNCTIONS] = true;
        }

        if ($node instanceof VectorDistance) {
            $found[Capability::VECTOR] = true;
        }

        if ($node instanceof Cte) {
            $found[Capability::CTE] = true;
            if ($node->recursive) {
                $found[Capability::RECURSIVE_CTE] = true;
            }
        }

        if ($node instanceof Join) {
            if ($node->type === JoinType::LeftLateral || $node->type === JoinType::InnerLateral) {
                $found[Capability::LATERAL] = true;
            }
            if ($node->type === JoinType::Full) {
                $found[Capability::FULL_JOIN] = true;
            }
        }

        if ($node instanceof JsonExpression) {
            $found[Capability::JSON] = true;
        }

        if ($node instanceof DateExpression) {
            $found[Capability::DATE_PART] = true;
        }

        if ($node instanceof Aggregate) {
            match ($node->function) {
                AggregateFunction::GroupConcat => $found[Capability::GROUP_CONCAT] = true,
                AggregateFunction::StringAgg => $found[Capability::STRING_AGG] = true,
                AggregateFunction::AnyValue => $found[Capability::ANY_VALUE] = true,
                AggregateFunction::Percentile, AggregateFunction::Median => $found[Capability::PERCENTILE] = true,
                default => null,
            };
        }

        if ($node instanceof Exists) {
            // EXISTS is portable across every supported relational engine.
        }

        if ($node instanceof InsertQuery) {
            if ($node->isUpsert()) {
                $found[Capability::UPSERT] = true;
            }
            if ($node->isIgnore()) {
                $found[Capability::INSERT_IGNORE] = true;
            }
            if ($node->isReplace()) {
                $found[Capability::INSERT_REPLACE] = true;
            }
            if ($node->returningColumns() !== null) {
                $found[Capability::RETURNING] = true;
            }
        }

        if ($node instanceof UpdateQuery && $node->returningColumns() !== null) {
            $found[Capability::RETURNING] = true;
        }

        if ($node instanceof DeleteQuery && $node->returningColumns() !== null) {
            $found[Capability::RETURNING] = true;
        }

        if ($node instanceof UpdateQuery && $node->limitValue() !== null) {
            $found[Capability::UPDATE_LIMIT] = true;
        }

        if ($node instanceof DeleteQuery && $node->limitValue() !== null) {
            $found[Capability::DELETE_LIMIT] = true;
        }

        if ($node instanceof SelectQuery && $node->lockMode() !== null) {
            $mode = strtolower($node->lockMode());
            $found[Capability::LOCK] = true;
            if (str_contains($mode, 'skip locked')) {
                $found[Capability::SKIP_LOCKED] = true;
            }
            if (str_contains($mode, 'nowait')) {
                $found[Capability::NOWAIT] = true;
            }
        }

        // Comparison operators with special capabilities.
        if ($node instanceof Comparison) {
            match ($node->operator) {
                ComparisonOperator::JsonContains, ComparisonOperator::JsonContainsKey => $found[Capability::JSON] = true,
                ComparisonOperator::ILike => $found[Capability::ILIKE] = true,
                ComparisonOperator::Regex => $found[Capability::REGEXP] = true,
                ComparisonOperator::IsDistinctFrom, ComparisonOperator::IsNotDistinctFrom => $found[Capability::IS_DISTINCT_FROM] = true,
                default => null,
            };
        }
    }
}
