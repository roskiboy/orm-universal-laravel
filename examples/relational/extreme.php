<?php

declare(strict_types=1);

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\BatchPlanner;
use OrmUniversal\Relational\Execution\QueryExecutor;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function relational_extreme_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

echo "== relational/extreme: portability, performance and capabilities ==\n";

$connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-extreme');
$dialect = SqliteDialect::make('3.49.2');
$executor = new QueryExecutor($connection, $dialect);

$executor->execute(new RawQuery(
    'create table orders (id integer primary key autoincrement, customer text not null, total numeric not null default 0, status text not null default \'pending\')',
));

$orders = static fn (): QueryBuilder => QueryBuilder::table($executor, 'orders');
$orders()->insertMany([
    ['customer' => 'ana', 'total' => 120, 'status' => 'paid'],
    ['customer' => 'bob', 'total' => 80, 'status' => 'pending'],
    ['customer' => 'carla', 'total' => 200, 'status' => 'paid'],
]);

$portable = $orders()->select('customer', 'total')->where('status', 'paid')->orderByDesc('total');
$portability = $portable->portability();
relational_extreme_assert($portability->percent() === 100.0, 'portability() reports 100%');
relational_extreme_assert($portability->classification()->value === 'portable', 'portable classification');
relational_extreme_assert($portability->isPortable(), 'isPortable() matches');
relational_extreme_assert($portability->toArray()['differences'] === [], 'no portability differences');

$dialectSpecific = $orders()->select(Column::make('total')->sum()->as('revenue'))->portability();
relational_extreme_assert($dialectSpecific->percent() <= 100.0, 'aggregates remain measurable in portability');

$performance = $orders()->where('status', 'paid')->performance();
relational_extreme_assert($performance->estimatedComplexity === 'low', 'performance() estimates low complexity');
relational_extreme_assert($performance->streamingAvailable, 'streaming available');
relational_extreme_assert(array_key_exists('hydration_cost', $performance->toArray()), 'performance() serializes');

$lightPerformance = $orders()->light()->performance();
relational_extreme_assert($lightPerformance->hydrationCost === 'low', 'light() changes hydration cost to low');

$capabilities = $orders()->capabilities();
relational_extreme_assert($capabilities->supports(Capability::RETURNING), 'SQLite 3.49.2 supports RETURNING');
relational_extreme_assert($capabilities->value(Capability::MAX_PLACEHOLDERS) === 32766, 'real placeholder budget');
relational_extreme_assert($dialect->limitations() !== [], 'limitations() documents real limits');

$explain = $orders()->where('status', 'paid')->explain();
relational_extreme_assert(count($explain) > 0, 'explain() returns the real plan (not simulated)');

$fingerprint = $orders()->select('customer')->where('status', 'paid')->fingerprint();
relational_extreme_assert(strlen((string) $fingerprint) === 64, 'fingerprint() produces a sha256 hash');
relational_extreme_assert($fingerprint->structuralHash !== null, 'fingerprint() includes the AST structural hash');

$planner = new BatchPlanner($dialect);
$report = $planner->report();
relational_extreme_assert($report['dialect'] === 'sqlite' && $report['version'] === '3.49.2', 'BatchPlanner reports dialect and version');
relational_extreme_assert($planner->maxRowsPerBatch(4) === 4095, 'maxRowsPerBatch() respects the budget with the safety factor');
relational_extreme_assert(count($planner->chunkRows(array_fill(0, 10, ['a' => 1]), 1)) === 1, 'chunkRows() splits by engine limit');

$unsupported = false;
try {
    $capabilities->requireAll([Capability::LATERAL], 'sqlite', '3.49.2', 'SQLite does not implement LATERAL joins');
} catch (UnsupportedFeatureException $exception) {
    $unsupported = true;
    echo '[OK] LATERAL not supported: '.str_replace(PHP_EOL, ' | ', $exception->report())."\n";
}
relational_extreme_assert($unsupported, 'missing capability throws UnsupportedFeatureException');

relational_extreme_assert($orders()->ast()->nodeType() === 'SelectQuery', 'ast() exposes the root node');
relational_extreme_assert($orders()->dialect()->name() === 'sqlite-3.49.2', 'dialect() exposes the version');

echo "relational/extreme OK\n";
exit(0);
