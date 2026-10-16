<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use Closure;
use OrmUniversal\Contracts\Enums\LogicalOperator;

/**
 * Predicate group used by closures: where(function (PredicateGroup $group) {...}).
 * Closures are first class citizens and never executed before compilation.
 *
 * @api public
 */
final class PredicateGroup
{
    /**
     * @var list<array{operator: LogicalOperator, expression: Expression}>
     */
    private array $parts = [];

    public function where(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->add(LogicalOperator::And, $column, $operator, $value);
    }

    public function orWhere(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->add(LogicalOperator::Or, $column, $operator, $value);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->parts[] = ['operator' => LogicalOperator::And, 'expression' => Column::make($column)->in($values)];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->parts[] = ['operator' => LogicalOperator::And, 'expression' => Column::make($column)->isNull()];

        return $this;
    }

    public function whereColumn(string $first, string $operator, string $second): self
    {
        $this->parts[] = [
            'operator' => LogicalOperator::And,
            'expression' => Comparison::of(Column::make($first), $operator, Column::make($second)),
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->parts[] = ['operator' => LogicalOperator::And, 'expression' => Column::make($column)->isNotNull()];

        return $this;
    }

    public function whereBetween(string $column, mixed $low, mixed $high): self
    {
        $this->parts[] = ['operator' => LogicalOperator::And, 'expression' => Column::make($column)->between($low, $high)];

        return $this;
    }

    public function toExpression(): ?Expression
    {
        if ($this->parts === []) {
            return null;
        }

        $current = null;
        foreach ($this->parts as $part) {
            if ($current === null) {
                $current = $part['expression'];

                continue;
            }

            $current = new LogicalExpression($part['operator'], [$current, $part['expression']]);
        }

        return $current;
    }

    private function add(LogicalOperator $operator, Expression|Closure|string $column, mixed $op, mixed $value): self
    {
        if ($column instanceof Closure) {
            $group = new self;
            $column($group);
            $expression = $group->toExpression();

            if ($expression !== null) {
                $this->parts[] = ['operator' => $operator, 'expression' => $expression];
            }

            return $this;
        }

        $this->parts[] = ['operator' => $operator, 'expression' => self::predicate($column, $op, $value)];

        return $this;
    }

    public static function predicate(Expression|string $column, mixed $operator, mixed $value): Expression
    {
        $expression = $column instanceof Expression ? $column : Column::fromPath($column);

        if ($operator === null) {
            return $expression;
        }

        if (is_array($operator)) {
            return $expression->in($operator);
        }

        if (is_string($operator) && self::isOperator($operator)) {
            return match (strtolower(trim($operator))) {
                '=', 'eq' => $expression->eq($value),
                '!=', '<>', 'neq' => $expression->neq($value),
                '>', 'gt' => $expression->gt($value),
                '>=', 'gte' => $expression->gte($value),
                '<', 'lt' => $expression->lt($value),
                '<=', 'lte' => $expression->lte($value),
                'like' => $expression->like((string) $value),
                'not like' => $expression->notLike((string) $value),
                default => throw new \InvalidArgumentException("Unsupported operator [{$operator}]."),
            };
        }

        return $expression->eq($operator);
    }

    private static function isOperator(string $operator): bool
    {
        return in_array(strtolower(trim($operator)), [
            '=', '==', 'eq', '!=', '<>', 'neq', '>', 'gt', '>=', 'gte',
            '<', 'lt', '<=', 'lte', 'like', 'not like',
        ], true);
    }
}
