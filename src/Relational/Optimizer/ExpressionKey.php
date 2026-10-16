<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer;

use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\Value;

/**
 * Structural key for expressions: two expressions with the same key are
 * interchangeable for duplicate elimination.
 *
 * @api public
 */
final class ExpressionKey
{
    public static function of(Expression $expression): string
    {
        return match (true) {
            $expression instanceof Column => 'col:'.($expression->table !== null ? $expression->table.'.' : '').$expression->name,
            $expression instanceof Value => 'val:'.get_debug_type($expression->value).':'.json_encode($expression->value),
            default => self::generic($expression),
        };
    }

    private static function generic(Expression $expression): string
    {
        $parts = [$expression->nodeType()];

        foreach ($expression->children() as $child) {
            $parts[] = $child instanceof Expression ? self::of($child) : '?';
        }

        if ($expression instanceof Comparison) {
            $parts[] = $expression->operator->value;
        }

        if ($expression instanceof RawExpression) {
            $parts[] = $expression->fragment->sql;
        }

        return implode('|', $parts);
    }
}
