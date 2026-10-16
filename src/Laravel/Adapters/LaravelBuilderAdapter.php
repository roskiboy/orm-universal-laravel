<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Database\Eloquent\Builder;
use OrmUniversal\Contracts\RecordsetInterface;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Execution\QueryExecutor;

/**
 * Bridges an Eloquent Builder into a UDE QueryBuilder. It copies only the query
 * shapes it understands; unknown clauses are skipped (never guessed) so results
 * are never silently altered.
 *
 * @api public
 */
final class LaravelBuilderAdapter
{
    public function fromEloquent(Builder $builder, QueryExecutor $executor): QueryBuilder
    {
        $model = $builder->getModel();
        $query = QueryBuilder::table($executor, $model->getTable());

        $this->copyWheres($builder, $query);
        $this->copyOrderingAndRange($builder, $query);

        return $query;
    }

    /**
     * Runs the equivalent UDE query and returns a recordset. Use only when the
     * copied clauses cover the full Eloquent query.
     */
    public function recordset(Builder $builder, QueryExecutor $executor): RecordsetInterface
    {
        return $this->fromEloquent($builder, $executor)->recordset();
    }

    private function copyWheres(Builder $builder, QueryBuilder $query): void
    {
        $wheres = $builder->getQuery()->wheres;

        foreach ($wheres as $where) {
            $type = (string) ($where['type'] ?? '');
            $boolean = strtolower((string) ($where['boolean'] ?? 'and')) === 'or' ? 'orWhere' : 'where';

            match ($type) {
                'Basic' => $query->{$boolean}((string) $where['column'], (string) ($where['operator'] ?? '='), $where['value'] ?? null),
                'Null' => ($where['not'] ?? false) ? $query->whereNotNull((string) $where['column']) : $query->whereNull((string) $where['column']),
                'NotNull' => $query->whereNotNull((string) $where['column']),
                'In' => ($where['not'] ?? false)
                    ? $query->whereNotIn((string) $where['column'], (array) ($where['values'] ?? []))
                    : $query->whereIn((string) $where['column'], (array) ($where['values'] ?? [])),
                'Between' => $query->whereBetween((string) $where['column'], $where['values'][0] ?? null, $where['values'][1] ?? null),
                'Column' => $query->whereColumn((string) $where['first'], (string) $where['operator'], (string) $where['second']),
                default => null,
            };
        }
    }

    private function copyOrderingAndRange(Builder $builder, QueryBuilder $query): void
    {
        $base = $builder->getQuery();

        foreach ($base->orders ?? [] as $order) {
            if (isset($order['column'])) {
                $query->orderBy((string) $order['column'], (string) ($order['direction'] ?? 'asc'));
            }
        }

        if ($base->limit !== null) {
            $query->limit((int) $base->limit);
        }

        if ($base->offset !== null) {
            $query->offset((int) $base->offset);
        }
    }
}
