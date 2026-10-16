<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer\Pass;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\InList;
use OrmUniversal\Relational\Ast\SelectQuery;

/**
 * Single-element IN/NOT IN lists become equality comparisons: `x in (1)` is
 * exactly `x = 1` (and `x not in (1)` is `x != 1`), including NULL semantics.
 * Multi-value lists and subqueries are left untouched.
 *
 * @api public
 */
final class InToEqualsPass implements OptimizerPassInterface
{
    public function name(): string
    {
        return 'in-to-equals';
    }

    public function apply(AstNodeInterface $node): AstNodeInterface
    {
        if (! $node instanceof InList
            || $node->values instanceof SelectQuery
            || count($node->values) !== 1
        ) {
            return $node;
        }

        return Comparison::of(
            $node->expression,
            $node->negated ? '!=' : '=',
            Expression::normalizeOperand($node->values[0]),
        );
    }
}
