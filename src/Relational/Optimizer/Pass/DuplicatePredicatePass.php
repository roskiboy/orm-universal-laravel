<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer\Pass;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Optimizer\ExpressionKey;

/**
 * Removes duplicated predicates from AND/OR groups. Semantics are preserved
 * because idempotence holds for boolean connectives.
 *
 * @api public
 */
final class DuplicatePredicatePass implements OptimizerPassInterface
{
    public function name(): string
    {
        return 'duplicate-predicates';
    }

    public function apply(AstNodeInterface $node): AstNodeInterface
    {
        if (! $node instanceof LogicalExpression) {
            return $node;
        }

        $seen = [];
        $parts = [];

        foreach ($node->parts() as $part) {
            $part = $part instanceof Expression ? $this->recurse($part) : $part;
            $key = ExpressionKey::of($part);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $parts[] = $part;
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return new LogicalExpression($node->operator, $parts);
    }

    private function recurse(Expression $expression): Expression
    {
        if ($expression instanceof LogicalExpression
            && in_array($expression->operator, [LogicalOperator::And, LogicalOperator::Or], true)
        ) {
            /** @var LogicalExpression $optimized */
            $optimized = $this->apply($expression);

            return $optimized instanceof Expression ? $optimized : $expression;
        }

        return $expression;
    }
}
