<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer\Pass;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\RawExpression;

/**
 * Simplifies boolean connectives with literal truths: `x and true` => `x`,
 * `x or true` => `true`, an empty AND group => `1 = 1`.
 *
 * @api public
 */
final class BooleanSimplificationPass implements OptimizerPassInterface
{
    public function name(): string
    {
        return 'boolean-simplification';
    }

    public function apply(AstNodeInterface $node): AstNodeInterface
    {
        if (! $node instanceof LogicalExpression) {
            return $node;
        }

        $isAnd = $node->operator === LogicalOperator::And;
        $isOr = $node->operator === LogicalOperator::Or;

        if (! $isAnd && ! $isOr) {
            return $node;
        }

        $parts = [];

        foreach ($node->parts() as $part) {
            if ($this->isLiteralTrue($part)) {
                if ($isOr) {
                    return RawExpression::trusted('1 = 1');
                }

                continue;
            }

            if ($this->isLiteralFalse($part) && $isAnd) {
                return RawExpression::trusted('1 = 0');
            }

            $parts[] = $part;
        }

        if ($parts === []) {
            return RawExpression::trusted($isOr ? '1 = 0' : '1 = 1');
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        return new LogicalExpression($node->operator, $parts);
    }

    private function isLiteralTrue(Expression $expression): bool
    {
        return $expression instanceof RawExpression
            && in_array(strtolower(trim($expression->fragment->sql)), ['1 = 1', 'true', '1'], true);
    }

    private function isLiteralFalse(Expression $expression): bool
    {
        return $expression instanceof RawExpression
            && in_array(strtolower(trim($expression->fragment->sql)), ['1 = 0', 'false', '0'], true);
    }
}
