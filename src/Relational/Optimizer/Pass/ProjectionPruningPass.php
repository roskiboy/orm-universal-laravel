<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer\Pass;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Optimizer\ExpressionKey;

/**
 * Projection pruning: removes duplicated projected expressions. Never removes a
 * column that appears only once and never touches aggregate semantics.
 *
 * @api public
 */
final class ProjectionPruningPass implements OptimizerPassInterface
{
    public function name(): string
    {
        return 'projection-pruning';
    }

    public function apply(AstNodeInterface $node): AstNodeInterface
    {
        if (! $node instanceof SelectQuery || count($node->columns()) <= 1) {
            return $node;
        }

        $seen = [];
        $columns = [];

        foreach ($node->columns() as $column) {
            $key = ExpressionKey::of($column).':'.($column->alias() ?? '');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $columns[] = $column;
        }

        if (count($columns) === count($node->columns())) {
            return $node;
        }

        return $node->select(...$columns);
    }
}
