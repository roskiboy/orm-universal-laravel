<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\Enums\SortDirection;
use OrmUniversal\Contracts\ExpressionInterface;
use OrmUniversal\Contracts\ValueObjects\Ordering;
use OrmUniversal\Contracts\ValueObjects\SqlFragment;

/**
 * Base class for every expression and the object oriented API entry point.
 *
 * @api public
 */
abstract class Expression extends Node implements ExpressionInterface
{
    protected ?string $alias = null;

    public function as(string $alias): static
    {
        $clone = clone $this;
        $clone->alias = $alias;

        return $clone;
    }

    public function alias(): ?string
    {
        return $this->alias;
    }

    // --- Static factories -------------------------------------------------

    public static function column(string $name): Column
    {
        return Column::make($name);
    }

    public static function value(mixed $value): Value
    {
        return new Value($value);
    }

    public static function param(?string $name = null): Parameter
    {
        return new Parameter($name);
    }

    public static function raw(string $sql): RawExpression
    {
        return RawExpression::trusted($sql);
    }

    /**
     * Normalize a vector input (array, JSON array string or comma-separated
     * string) to the JSON array text both MariaDB and pgvector accept.
     */
    public static function normalizeVector(mixed $vector): string
    {
        if (is_array($vector)) {
            return json_encode(array_values($vector), JSON_THROW_ON_ERROR);
        }

        if ($vector instanceof \Traversable) {
            return json_encode(array_values(iterator_to_array($vector)), JSON_THROW_ON_ERROR);
        }

        if (is_string($vector)) {
            $trimmed = trim($vector);

            if ($trimmed !== '' && str_starts_with($trimmed, '[')) {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

                return json_encode(array_values((array) $decoded), JSON_THROW_ON_ERROR);
            }

            $numbers = [];
            foreach (explode(',', $trimmed) as $part) {
                $part = trim($part);
                $numbers[] = is_numeric($part) ? $part + 0 : $part;
            }

            return json_encode($numbers, JSON_THROW_ON_ERROR);
        }

        throw new \InvalidArgumentException('Unsupported vector value; pass an array or a JSON/comma separated string.');
    }

    public static function fragment(SqlFragment $fragment): RawExpression
    {
        return new RawExpression($fragment);
    }

    public static function func(string $name, mixed ...$arguments): FunctionCall
    {
        return new FunctionCall($name, $arguments);
    }

    public static function json(string|self $column, string $path, bool $asText = false): JsonExpression
    {
        return new JsonExpression($column, $path, $asText);
    }

    public static function date(string $part, string|self $column): DateExpression
    {
        return new DateExpression($part, $column);
    }

    // --- Comparisons ------------------------------------------------------

    public function eq(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::Eq, self::normalizeOperand($value));
    }

    public function neq(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::Neq, self::normalizeOperand($value));
    }

    public function gt(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::Gt, self::normalizeOperand($value));
    }

    public function gte(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::Gte, self::normalizeOperand($value));
    }

    public function lt(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::Lt, self::normalizeOperand($value));
    }

    public function lte(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::Lte, self::normalizeOperand($value));
    }

    public function like(string $pattern): Comparison
    {
        return new Comparison($this, ComparisonOperator::Like, new Value($pattern));
    }

    public function notLike(string $pattern): Comparison
    {
        return new Comparison($this, ComparisonOperator::NotLike, new Value($pattern));
    }

    public function isNull(): Comparison
    {
        return new Comparison($this, ComparisonOperator::IsNull, null);
    }

    public function isNotNull(): Comparison
    {
        return new Comparison($this, ComparisonOperator::IsNotNull, null);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function in(array $values): InList
    {
        return new InList($this, $values, false);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function notIn(array $values): InList
    {
        return new InList($this, $values, true);
    }

    public function between(mixed $low, mixed $high): Between
    {
        return new Between($this, self::normalizeOperand($low), self::normalizeOperand($high), false);
    }

    public function notBetween(mixed $low, mixed $high): Between
    {
        return new Between($this, self::normalizeOperand($low), self::normalizeOperand($high), true);
    }

    public function logical(LogicalOperator $operator, Expression ...$expressions): LogicalExpression
    {
        return new LogicalExpression($operator, array_merge([$this], $expressions));
    }

    public function and(Expression ...$expressions): LogicalExpression
    {
        return new LogicalExpression(LogicalOperator::And, array_merge([$this], $expressions));
    }

    public function or(Expression ...$expressions): LogicalExpression
    {
        return new LogicalExpression(LogicalOperator::Or, array_merge([$this], $expressions));
    }

    // --- Aggregates -------------------------------------------------------

    public function count(): Aggregate
    {
        return new Aggregate(AggregateFunction::Count, $this);
    }

    public function sum(): Aggregate
    {
        return new Aggregate(AggregateFunction::Sum, $this);
    }

    public function avg(): Aggregate
    {
        return new Aggregate(AggregateFunction::Avg, $this);
    }

    public function min(): Aggregate
    {
        return new Aggregate(AggregateFunction::Min, $this);
    }

    public function max(): Aggregate
    {
        return new Aggregate(AggregateFunction::Max, $this);
    }

    // --- Ordering ---------------------------------------------------------

    public function asc(): Ordering
    {
        return new Ordering($this, SortDirection::Asc);
    }

    public function desc(): Ordering
    {
        return new Ordering($this, SortDirection::Desc);
    }

    public static function normalizeOperand(mixed $value): Expression
    {
        if ($value instanceof Expression) {
            return $value;
        }

        if ($value instanceof SqlFragment) {
            return new RawExpression($value);
        }

        return new Value($value);
    }
}
