<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\SelectQuery;

/**
 * AST normalizer: canonicalizes boolean groups (flattening associative AND/OR)
 * and nested wrappers without changing semantics. Runs before validation and
 * optimization in the documented pipeline.
 *
 * @api public
 */
final class Normalizer
{
    public function normalize(AstNodeInterface $node): AstNodeInterface
    {
        if ($node instanceof SelectQuery) {
            if (($where = $node->whereExpression()) !== null) {
                $node->replaceWhere($this->normalizeExpression($where));
            }

            if (($having = $node->havingExpression()) !== null) {
                $node->replaceHaving($this->normalizeExpression($having));
            }
        }

        return $node;
    }

    public function normalizeExpression(Expression $expression): Expression
    {
        if (! $expression instanceof LogicalExpression) {
            return $expression;
        }

        $flattened = [];
        foreach ($expression->parts() as $part) {
            $normalized = $this->normalizeExpression($part);

            if ($normalized instanceof LogicalExpression
                && $normalized->operator === $expression->operator
                && in_array($expression->operator, [LogicalOperator::And, LogicalOperator::Or], true)
            ) {
                foreach ($normalized->parts() as $nested) {
                    $flattened[] = $nested;
                }

                continue;
            }

            $flattened[] = $normalized;
        }

        if (count($flattened) === 1) {
            return $flattened[0];
        }

        return new LogicalExpression($expression->operator, $flattened);
    }
}
