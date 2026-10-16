<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer\Pass;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\Value;

/**
 * Folds comparisons between two literals into a constant predicate.
 *
 * @api public
 */
final class ConstantFoldingPass implements OptimizerPassInterface
{
    public function name(): string
    {
        return 'constant-folding';
    }

    public function apply(AstNodeInterface $node): AstNodeInterface
    {
        if (! $node instanceof Comparison || ! $node->left instanceof Value || ! $node->right instanceof Value) {
            return $node;
        }

        $left = $node->left->value;
        $right = $node->right->value;

        $result = match ($node->operator) {
            ComparisonOperator::Eq => $left == $right,
            ComparisonOperator::Neq => $left != $right,
            ComparisonOperator::Gt => $left > $right,
            ComparisonOperator::Gte => $left >= $right,
            ComparisonOperator::Lt => $left < $right,
            ComparisonOperator::Lte => $left <= $right,
            ComparisonOperator::IsNull => $left === null,
            ComparisonOperator::IsNotNull => $left !== null,
            default => null,
        };

        if ($result === null) {
            return $node;
        }

        return RawExpression::trusted($result ? '1 = 1' : '1 = 0');
    }
}
