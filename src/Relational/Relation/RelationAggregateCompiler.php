<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use Closure;
use InvalidArgumentException;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Exists;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\Subquery;

/**
 * Compiles relation aggregates (withCount/withSum/withAvg/withMin/withMax) and
 * relation EXISTS filters as correlated subqueries. Semantics match Eloquent:
 * count = 0, sum/avg/min/max = null when empty, exists = false.
 *
 * @api public
 */
final class RelationAggregateCompiler
{
    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public static function aggregate(
        RelationDefinition $definition,
        string $parentTable,
        string $function,
        string $column = '*',
        ?Closure $constraints = null,
        bool $distinct = false,
        ?string $alias = null,
    ): Expression {
        $related = $definition->relatedTable()->name;
        $subquery = SelectQuery::make($related);

        $aggregate = match (strtolower($function)) {
            'count' => new Aggregate(
                $distinct ? AggregateFunction::CountDistinct : AggregateFunction::Count,
                $column === '*' ? '*' : Column::of($related, $column),
            ),
            'sum' => new Aggregate(AggregateFunction::Sum, Column::of($related, $column)),
            'avg' => new Aggregate(AggregateFunction::Avg, Column::of($related, $column)),
            'min' => new Aggregate(AggregateFunction::Min, Column::of($related, $column)),
            'max' => new Aggregate(AggregateFunction::Max, Column::of($related, $column)),
            default => throw new InvalidArgumentException("Unsupported relation aggregate [{$function}]."),
        };

        $subquery->select($aggregate);
        $subquery->whereColumn(
            $related.'.'.$definition->foreignKey(),
            '=',
            $parentTable.'.'.$definition->localKey(),
        );

        self::applySoftDeletes($subquery, $definition, $related);

        if ($constraints !== null) {
            $constraints($subquery);
        }

        $expression = new Subquery($subquery);

        return $alias !== null ? $expression->as($alias) : $expression;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public static function exists(
        RelationDefinition $definition,
        string $parentTable,
        ?Closure $constraints = null,
        bool $negated = false,
        ?string $alias = null,
    ): Expression {
        $related = $definition->relatedTable()->name;
        $subquery = SelectQuery::make($related)->select(RawExpression::trusted('1'));

        $subquery->whereColumn(
            $related.'.'.$definition->foreignKey(),
            '=',
            $parentTable.'.'.$definition->localKey(),
        );

        self::applySoftDeletes($subquery, $definition, $related);

        if ($constraints !== null) {
            $constraints($subquery);
        }

        $expression = new Exists($subquery, $negated);

        return $alias !== null ? $expression->as($alias) : $expression;
    }

    private static function applySoftDeletes(SelectQuery $subquery, RelationDefinition $definition, string $related): void
    {
        if (($definition->metadata()['soft_deletes'] ?? false) === true) {
            $subquery->whereNull($related.'.deleted_at');
        }
    }
}
