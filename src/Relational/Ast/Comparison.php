<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\ComparisonOperator;

/**
 * Binary comparison such as column = value or json_contains(...).
 * IsNull / IsNotNull carry a null right hand side.
 *
 * @api public
 */
final class Comparison extends Expression
{
    public function __construct(
        public readonly Expression $left,
        public readonly ComparisonOperator $operator,
        public readonly ?Expression $right = null,
    ) {}

    public function nodeType(): string
    {
        return 'Comparison';
    }

    /**
     * Build a comparison from a SQL operator string.
     */
    public static function of(Expression $left, string $operator, Expression $right): self
    {
        $normalized = strtolower(trim($operator));

        $mapped = match ($normalized) {
            '=', '==', 'eq' => ComparisonOperator::Eq,
            '!=', '<>', 'neq' => ComparisonOperator::Neq,
            '>', 'gt' => ComparisonOperator::Gt,
            '>=', 'gte' => ComparisonOperator::Gte,
            '<', 'lt' => ComparisonOperator::Lt,
            '<=', 'lte' => ComparisonOperator::Lte,
            'like' => ComparisonOperator::Like,
            'not like' => ComparisonOperator::NotLike,
            'ilike' => ComparisonOperator::ILike,
            'is distinct from' => ComparisonOperator::IsDistinctFrom,
            'is not distinct from' => ComparisonOperator::IsNotDistinctFrom,
            default => throw new \InvalidArgumentException("Unsupported comparison operator [{$operator}]."),
        };

        return new self($left, $mapped, $right);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return $this->right === null ? [$this->left] : [$this->left, $this->right];
    }
}
