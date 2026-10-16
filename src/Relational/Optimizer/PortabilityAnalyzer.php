<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Enums\PortabilityClass;
use OrmUniversal\Contracts\ValueObjects\PortabilityReport;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Cte;
use OrmUniversal\Relational\Ast\DateExpression;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\Join;
use OrmUniversal\Relational\Ast\JsonExpression;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\WindowExpression;

/**
 * Portability analysis: classifies every node of a query as portable,
 * partially portable or dialect specific and reports human differences.
 *
 * @api public
 */
final class PortabilityAnalyzer
{
    public function analyze(AstNodeInterface $node): PortabilityReport
    {
        $nodes = [];
        $differences = [];
        $this->walk($node, $nodes, $differences);

        return new PortabilityReport($nodes, array_values(array_unique($differences)));
    }

    /**
     * @param  array<string, PortabilityClass>  $nodes
     * @param  list<string>  $differences
     */
    private function walk(AstNodeInterface $node, array &$nodes, array &$differences): void
    {
        if ($node instanceof JsonExpression) {
            $nodes['json'] = PortabilityClass::PartiallyPortable;
            $differences[] = 'JSON access compiles to vendor functions (JSON_EXTRACT / #>> / json_extract / JSON_VALUE).';
        }

        if ($node instanceof DateExpression) {
            $nodes['date-part'] = PortabilityClass::PartiallyPortable;
            $differences[] = 'Date part extraction maps to EXTRACT / DATE_PART(strftime) / DATEPART.';
        }

        if ($node instanceof WindowExpression) {
            $nodes['window'] = PortabilityClass::Portable;
        }

        if ($node instanceof Aggregate) {
            match ($node->function) {
                AggregateFunction::GroupConcat,
                AggregateFunction::StringAgg => $this->mark($nodes, $differences, 'string-aggregation', 'String aggregation uses GROUP_CONCAT, STRING_AGG or LISTAGG depending on the vendor.'),
                AggregateFunction::AnyValue => $this->mark($nodes, $differences, 'any-value', 'ANY_VALUE exists on MySQL/MariaDB; other engines require MIN/MAX.'),
                default => null,
            };
        }

        if ($node instanceof Comparison) {
            match ($node->operator) {
                ComparisonOperator::ILike => $this->mark($nodes, $differences, 'ilike', 'ILIKE is native on PostgreSQL; other engines compile LOWER(x) LIKE LOWER(y).'),
                ComparisonOperator::Regex => $this->mark($nodes, $differences, 'regex', 'Regex uses REGEXP / ~ / REGEXP_LIKE depending on the vendor.'),
                ComparisonOperator::IsDistinctFrom,
                ComparisonOperator::IsNotDistinctFrom => $this->mark($nodes, $differences, 'distinct-from', 'IS [NOT] DISTINCT FROM is emulated with null-safe comparisons where absent.'),
                ComparisonOperator::JsonContains,
                ComparisonOperator::JsonContainsKey => $this->mark($nodes, $differences, 'json-contains', 'JSON containment support varies per engine/version.'),
                default => null,
            };
        }

        if ($node instanceof Join && in_array($node->type, [JoinType::LeftLateral, JoinType::InnerLateral], true)) {
            $this->mark($nodes, $differences, 'lateral', 'LATERAL joins are not available on every supported engine.');
        }

        if ($node instanceof SelectQuery && $node->lockMode() !== null) {
            $this->mark($nodes, $differences, 'row-locking', 'Row locking clauses are dialect specific (FOR UPDATE / hints).');
        }

        if ($node instanceof Cte && $node->recursive) {
            $nodes['recursive-cte'] = PortabilityClass::Portable;
        }

        if ($node instanceof InsertQuery && $node->isUpsert()) {
            $this->mark($nodes, $differences, 'upsert', 'Upserts compile to ON DUPLICATE KEY, ON CONFLICT or MERGE.');
        }

        if ($node instanceof InsertQuery && $node->returningColumns() !== null) {
            $this->mark($nodes, $differences, 'returning', 'RETURNING is emulated with OUTPUT or not available (MySQL).');
        }

        foreach ($node->children() as $child) {
            $this->walk($child, $nodes, $differences);
        }
    }

    /**
     * @param  array<string, PortabilityClass>  $nodes
     * @param  list<string>  $differences
     */
    private function mark(array &$nodes, array &$differences, string $key, string $difference): void
    {
        $nodes[$key] = PortabilityClass::PartiallyPortable;
        $differences[] = $difference;
    }
}
