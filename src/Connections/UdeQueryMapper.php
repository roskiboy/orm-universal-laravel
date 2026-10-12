<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use DateTimeInterface;
use Illuminate\Contracts\Database\Query\Expression as LaravelExpression;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as IlluminateQueryBuilder;
use Illuminate\Database\Query\JoinClause;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Between;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\InList;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\PredicateGroup;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\SelectQuery;

/**
 * Translates the whitelisted subset of an Illuminate query builder into a UDE
 * SelectQuery AST. The whitelist is strict: any clause that is not fully
 * understood (raw expressions, subqueries, unions, locks, expressions in
 * identifiers or values) makes the mapper return null so the caller can fall
 * back to Laravel without changing results.
 *
 * @api public
 */
final class UdeQueryMapper
{
    private const COLUMN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    private const COLUMN_STAR = '/^[A-Za-z_][A-Za-z0-9_]*\.\*$/';

    private const COLUMN_AS = '/^([A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i';

    private const TABLE_AS = '/^([A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?)\s+(?:as\s+)?([A-Za-z_][A-Za-z0-9_]*)$/i';

    /** @var list<string> */
    private const COMPARISONS = ['=', '!=', '<>', '>', '>=', '<', '<='];

    /** @var list<string> */
    private const OPERATORS = ['=', '!=', '<>', '>', '>=', '<', '<=', 'like', 'not like'];

    public function map(IlluminateQueryBuilder $builder): ?SelectQuery
    {
        if (! $builder->connection instanceof Connection) {
            return null;
        }

        if ((string) $builder->connection->getTablePrefix() !== '') {
            return null;
        }

        if (! is_string($builder->from)) {
            return null;
        }

        if ($builder->unions !== null && $builder->unions !== []) {
            return null;
        }

        if ($builder->unionOrders !== null || $builder->unionLimit !== null || $builder->unionOffset !== null) {
            return null;
        }

        if ($builder->lock !== null || $builder->groupLimit !== null || $builder->indexHint !== null) {
            return null;
        }

        if ($builder->distinct !== false && $builder->distinct !== true) {
            return null;
        }

        if ($builder->aggregate !== null && (($builder->groups ?? []) !== [] || ($builder->havings ?? []) !== [])) {
            return null;
        }

        $table = $this->table($builder->from);
        if ($table === null) {
            return null;
        }

        $ast = SelectQuery::make($table);

        if ($builder->distinct === true) {
            $ast->distinct();
        }

        if (is_array($builder->columns)) {
            $columns = [];
            foreach ($builder->columns as $column) {
                $projection = $this->projection($column);
                if ($projection === null) {
                    return null;
                }
                $columns[] = $projection;
            }

            if ($columns !== []) {
                $ast->select(...$columns);
            }
        }

        foreach ($builder->joins ?? [] as $join) {
            if (! $this->applyJoin($ast, $join)) {
                return null;
            }
        }

        $where = $this->whereExpression($builder->wheres);
        if ($where === false) {
            return null;
        }

        if ($where !== null) {
            $ast->replaceWhere($where);
        }

        foreach ($builder->groups ?? [] as $group) {
            if (! is_string($group) || preg_match(self::COLUMN, $group) !== 1) {
                return null;
            }
            $ast->groupBy($group);
        }

        $having = $this->havingExpression($builder->havings ?? []);
        if ($having === false) {
            return null;
        }

        if ($having !== null) {
            $ast->replaceHaving($having);
        }

        if (is_array($builder->orders)) {
            foreach ($builder->orders as $order) {
                if (! $this->applyOrder($ast, $order)) {
                    return null;
                }
            }
        }

        if ($builder->aggregate !== null) {
            $projection = $this->aggregateProjection($builder);
            if ($projection === null) {
                return null;
            }
            $ast->select($projection);
            $ast->clearOrderings();
        }

        if ($builder->limit !== null) {
            if (! is_int($builder->limit)) {
                return null;
            }
            $ast->limit($builder->limit);
        }

        if ($builder->offset !== null) {
            if (! is_int($builder->offset)) {
                return null;
            }
            $ast->offset($builder->offset);
        }

        return $ast;
    }

    private function table(string $from): ?TableIdentifier
    {
        $from = trim($from);
        if (preg_match(self::COLUMN, $from) === 1) {
            return TableIdentifier::fromString($from);
        }

        if (preg_match(self::TABLE_AS, $from, $matches) === 1) {
            return TableIdentifier::fromString($matches[1], $matches[3]);
        }

        return null;
    }

    private function projection(mixed $column): ?Expression
    {
        if (! is_string($column)) {
            return null;
        }

        $column = trim($column);

        if ($column === '*') {
            return RawExpression::trusted('*');
        }

        if (preg_match(self::COLUMN_STAR, $column) === 1) {
            return RawExpression::trusted($column);
        }

        if (preg_match(self::COLUMN_AS, $column, $matches) === 1) {
            return Column::make($matches[1])->as($matches[3]);
        }

        if (preg_match(self::COLUMN, $column) === 1) {
            return Column::make($column);
        }

        return null;
    }

    private function applyJoin(SelectQuery $ast, mixed $join): bool
    {
        if (! $join instanceof JoinClause) {
            return false;
        }

        $type = strtolower((string) $join->type);
        if (! in_array($type, ['inner', 'left', 'right', 'cross'], true)) {
            return false;
        }

        if (! is_string($join->table)) {
            return false;
        }

        $table = $this->table($join->table);
        if ($table === null) {
            return false;
        }

        $wheres = $join->wheres;
        if ($type === 'cross') {
            if ($wheres !== []) {
                return false;
            }

            $ast->crossJoin($table);

            return true;
        }

        if (count($wheres) === 0) {
            return false;
        }

        $conditions = [];

        foreach ($wheres as $where) {
            if (($where['type'] ?? null) !== 'Column') {
                return false;
            }

            if (strtolower((string) ($where['boolean'] ?? 'and')) !== 'and') {
                return false;
            }

            $first = $where['first'] ?? null;
            $second = $where['second'] ?? null;
            $operator = strtolower((string) ($where['operator'] ?? '='));

            if (! is_string($first) || ! is_string($second)) {
                return false;
            }

            if (preg_match(self::COLUMN, $first) !== 1 || preg_match(self::COLUMN, $second) !== 1) {
                return false;
            }

            if (! in_array($operator, self::COMPARISONS, true)) {
                return false;
            }

            $conditions[] = [$first, $operator, $second];
        }

        $on = static function (PredicateGroup $group) use ($conditions): void {
            foreach ($conditions as [$first, $operator, $second]) {
                $group->whereColumn($first, $operator, $second);
            }
        };

        match ($type) {
            'left' => $ast->leftJoin($table, $on),
            'right' => $ast->rightJoin($table, $on),
            default => $ast->join($table, $on),
        };

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $wheres
     */
    private function whereExpression(array $wheres): Expression|false|null
    {
        $current = null;

        foreach ($wheres as $where) {
            $expression = $this->singleWhere($where);
            if ($expression === false) {
                return false;
            }

            if ($expression === null) {
                continue;
            }

            $operator = strtolower((string) ($where['boolean'] ?? 'and')) === 'or'
                ? LogicalOperator::Or
                : LogicalOperator::And;

            $current = $current === null
                ? $expression
                : new LogicalExpression($operator, [$current, $expression]);
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function singleWhere(array $where): Expression|false|null
    {
        $type = (string) ($where['type'] ?? '');
        $column = $where['column'] ?? null;

        switch ($type) {
            case 'Basic':
                if (! is_string($column) || preg_match(self::COLUMN, $column) !== 1) {
                    return false;
                }

                $operator = strtolower((string) ($where['operator'] ?? '='));
                if (! in_array($operator, self::OPERATORS, true)) {
                    return false;
                }

                $value = $where['value'] ?? null;
                if (! $this->isBindable($value)) {
                    return false;
                }

                return $operator === 'like'
                    ? Column::make($column)->like((string) $value)
                    : ($operator === 'not like'
                        ? Column::make($column)->notLike((string) $value)
                        : Column::make($column)->{match ($operator) {
                            '=' => 'eq',
                            '!=', '<>' => 'neq',
                            '>' => 'gt',
                            '>=' => 'gte',
                            '<' => 'lt',
                            default => 'lte',
                        }}($value));

            case 'Null':
            case 'NotNull':
                if (! is_string($column) || preg_match(self::COLUMN, $column) !== 1) {
                    return false;
                }

                return $type === 'Null'
                    ? Column::make($column)->isNull()
                    : Column::make($column)->isNotNull();

            case 'In':
            case 'NotIn':
                if (! is_string($column) || preg_match(self::COLUMN, $column) !== 1) {
                    return false;
                }

                $values = $where['values'] ?? null;
                if (! is_array($values) || $values === []) {
                    return false;
                }

                foreach ($values as $value) {
                    if (! $this->isBindable($value)) {
                        return false;
                    }
                }

                return new InList(Column::make($column), array_values($values), $type === 'NotIn');

            case 'between':
                if (! is_string($column) || preg_match(self::COLUMN, $column) !== 1) {
                    return false;
                }

                $values = $where['values'] ?? null;
                if (! is_array($values) || ! array_key_exists(0, $values) || ! array_key_exists(1, $values)) {
                    return false;
                }

                if (! $this->isBindable($values[0]) || ! $this->isBindable($values[1])) {
                    return false;
                }

                return new Between(
                    Column::make($column),
                    Expression::value($values[0]),
                    Expression::value($values[1]),
                    (bool) ($where['not'] ?? false),
                );

            case 'Column':
                $first = $where['first'] ?? null;
                $second = $where['second'] ?? null;
                $operator = strtolower((string) ($where['operator'] ?? '='));

                if (! is_string($first) || ! is_string($second)) {
                    return false;
                }

                if (preg_match(self::COLUMN, $first) !== 1 || preg_match(self::COLUMN, $second) !== 1) {
                    return false;
                }

                if (! in_array($operator, self::COMPARISONS, true)) {
                    return false;
                }

                return Comparison::of(Column::make($first), $operator, Column::make($second));

            case 'Nested':
                $nested = $where['query'] ?? null;
                if (! $nested instanceof IlluminateQueryBuilder) {
                    return false;
                }

                return $this->nestedWhere($nested);
        }

        return false;
    }

    private function nestedWhere(IlluminateQueryBuilder $nested): Expression|false|null
    {
        if ($nested->unions !== null && $nested->unions !== []) {
            return false;
        }

        if ($nested->unionOrders !== null || $nested->unionLimit !== null || $nested->unionOffset !== null) {
            return false;
        }

        if ($nested->joins !== null || $nested->groups !== null || $nested->havings !== null || $nested->orders !== null) {
            return false;
        }

        if ($nested->limit !== null || $nested->offset !== null || $nested->lock !== null) {
            return false;
        }

        if ($nested->aggregate !== null || $nested->groupLimit !== null || $nested->indexHint !== null) {
            return false;
        }

        return $this->whereExpression($nested->wheres);
    }

    /**
     * @param  array<int, array<string, mixed>>  $havings
     */
    private function havingExpression(array $havings): Expression|false|null
    {
        $current = null;

        foreach ($havings as $having) {
            $expression = $this->singleHaving($having);
            if ($expression === false) {
                return false;
            }

            if ($expression === null) {
                continue;
            }

            $operator = strtolower((string) ($having['boolean'] ?? 'and')) === 'or'
                ? LogicalOperator::Or
                : LogicalOperator::And;

            $current = $current === null
                ? $expression
                : new LogicalExpression($operator, [$current, $expression]);
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $having
     */
    private function singleHaving(array $having): Expression|false|null
    {
        if (($having['type'] ?? null) === 'Nested') {
            $nested = $having['query'] ?? null;
            if (! $nested instanceof IlluminateQueryBuilder) {
                return false;
            }

            if ($nested->unions !== null || $nested->joins !== null || $nested->orders !== null) {
                return false;
            }

            if ($nested->limit !== null || $nested->offset !== null || $nested->lock !== null) {
                return false;
            }

            return $this->havingExpression($nested->havings ?? []);
        }

        return $this->singleWhere($having);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function applyOrder(SelectQuery $ast, array $order): bool
    {
        $column = $order['column'] ?? null;
        if (! is_string($column) || preg_match(self::COLUMN, $column) !== 1) {
            return false;
        }

        $direction = strtolower((string) ($order['direction'] ?? 'asc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            return false;
        }

        $ast->orderBy($column, $direction);

        return true;
    }

    private function aggregateProjection(IlluminateQueryBuilder $builder): ?Expression
    {
        $aggregate = $builder->aggregate;
        if (! is_array($aggregate)) {
            return null;
        }

        $function = match (strtolower((string) ($aggregate['function'] ?? ''))) {
            'count' => AggregateFunction::Count,
            'sum' => AggregateFunction::Sum,
            'avg' => AggregateFunction::Avg,
            'min' => AggregateFunction::Min,
            'max' => AggregateFunction::Max,
            default => null,
        };

        if ($function === null) {
            return null;
        }

        $columns = $aggregate['columns'] ?? null;
        if (! is_array($columns) || count($columns) !== 1) {
            return null;
        }

        $column = $columns[0];
        if ($column === '*') {
            return (new Aggregate($function, '*'))->as('aggregate');
        }

        if (! is_string($column) || preg_match(self::COLUMN, $column) !== 1) {
            return null;
        }

        $distinct = $builder->distinct === true;

        return (new Aggregate($function, Column::make($column), $distinct))->as('aggregate');
    }

    private function isBindable(mixed $value): bool
    {
        if ($value === null || is_array($value) || $value instanceof LaravelExpression) {
            return false;
        }

        if (is_object($value) && ! $value instanceof DateTimeInterface) {
            return false;
        }

        return ! is_resource($value);
    }
}
