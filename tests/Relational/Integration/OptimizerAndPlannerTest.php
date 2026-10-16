<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Execution\BatchPlanner;
use OrmUniversal\Relational\Optimizer\Optimizer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * optimize-12: optimizer passes and adaptive batching.
 */
#[Group('integration')]
final class OptimizerAndPlannerTest extends TestCase
{
    public function test_default_passes_include_in_to_equals_and_projection_pruning(): void
    {
        self::assertSame(
            ['duplicate-predicates', 'boolean-simplification', 'constant-folding', 'in-to-equals', 'projection-pruning'],
            (new Optimizer)->passNames(),
        );
    }

    public function test_single_in_becomes_equality_and_multi_in_stays(): void
    {
        $dialect = SqliteDialect::make('3.49.2');
        $optimizer = new Optimizer;

        $single = SelectQuery::make('t')->whereIn('a', [1]);
        $optimized = $dialect->compile($optimizer->optimize($single));
        self::assertSame('select * from "t" where "a" = ?', $optimized->sql);
        self::assertSame([1], $optimized->bindings);

        $negated = SelectQuery::make('t')->whereNotIn('a', [1]);
        self::assertSame('select * from "t" where "a" <> ?', $dialect->compile($optimizer->optimize($negated))->sql);

        $multi = SelectQuery::make('t')->whereIn('a', [1, 2]);
        self::assertSame('select * from "t" where "a" in (?, ?)', $dialect->compile($optimizer->optimize($multi))->sql);

        $subquery = SelectQuery::make('t')->whereIn('a', SelectQuery::make('u')->select('id'));
        $optimizedSub = $optimizer->optimize($subquery);
        self::assertNotNull($optimizedSub);
        self::assertSame(
            'select * from "t" where "a" in (select "id" from "u")',
            $dialect->compile($optimizedSub)->sql,
        );
    }

    public function test_adaptive_batches_are_powers_of_two_within_budget(): void
    {
        $planner = new BatchPlanner(SqliteDialect::make('3.49.2'));

        foreach ([1, 2, 3, 7, 10, 33] as $columns) {
            $batch = $planner->maxRowsPerBatchAdaptive($columns);
            self::assertGreaterThanOrEqual(1, $batch);
            self::assertSame(0, $batch & ($batch - 1), 'batch must be a power of two');
            self::assertLessThanOrEqual($planner->maxRowsPerBatch($columns), $batch);
        }

        $chunks = $planner->chunkRowsAdaptive(array_fill(0, 10, ['a' => 1]), 1);
        self::assertSame(10, array_sum(array_map('count', $chunks)));
    }
}
