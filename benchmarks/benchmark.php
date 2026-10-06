<?php

declare(strict_types=1);

/**
 * Eloquent vs Query Builder vs UDE benchmark. SQLite in memory by default; set
 * UDE_MYSQL_* / UDE_PGSQL_* to point at a real server.
 *
 *   php benchmarks/benchmark.php --rows=5000 --json --markdown --assert-vs-pdo=3.0
 */
$autoload = __DIR__.'/../vendor/autoload.php';
if (! is_file($autoload)) {
    $autoload = __DIR__.'/../../../vendor/autoload.php';
}
require $autoload;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;

$options = getopt('', ['rows::', 'json', 'markdown', 'assert-vs-pdo::', 'output::']);
$rows = max(100, (int) ($options['rows'] ?? 5000));
$assertFactor = isset($options['assert-vs-pdo']) ? (float) $options['assert-vs-pdo'] : null;
$output = (string) ($options['output'] ?? __DIR__.'/results');

$capsule = new Capsule;
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$pdo = $capsule->getConnection()->getPdo();
$pdo->exec('create table bench_orders (id integer primary key, customer varchar(100) not null, total decimal(10,2) not null, status varchar(20) not null)');

$insert = $pdo->prepare('insert into bench_orders (id, customer, total, status) values (?, ?, ?, ?)');
$pdo->beginTransaction();
for ($i = 1; $i <= $rows; $i++) {
    $insert->execute([$i, 'customer'.($i % 500), number_format(10 + ($i % 900) / 10, 2, '.', ''), $i % 3 === 0 ? 'paid' : 'pending']);
    if ($i % 1000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

class BenchOrder extends Model
{
    protected $table = 'bench_orders';

    public $timestamps = false;

    protected $casts = ['total' => 'decimal:2'];
}

$dialect = DialectResolver::resolve('sqlite', (string) $pdo->query('select sqlite_version()')->fetchColumn());
$executor = new QueryExecutor(new PdoConnection($pdo, 'bench-laravel'), $dialect);

$results = [];
$measure = static function (string $label, callable $operation) use (&$results): void {
    $memoryBefore = memory_get_usage();
    $started = microtime(true);
    $count = $operation();
    $elapsed = (microtime(true) - $started) * 1000;

    $results[] = [
        'mode' => $label,
        'rows' => $count,
        'time_ms' => round($elapsed, 3),
        'rows_per_sec' => $elapsed > 0 ? (int) round(($count / $elapsed) * 1000) : 0,
        'memory_kb' => round((memory_get_usage() - $memoryBefore) / 1024, 1),
    ];
};

$measure('eloquent_models', fn (): int => BenchOrder::query()->get()->count());
$measure('eloquent_to_array', fn (): int => count(BenchOrder::query()->get()->toArray()));
$measure('query_builder', fn (): int => Capsule::table('bench_orders')->get()->count());
$measure('pdo_fetch_all', fn (): int => count($pdo->query('select * from bench_orders')->fetchAll(PDO::FETCH_ASSOC)));
$measure('ude_zero', fn (): int => count(QueryBuilder::table($executor, 'bench_orders')->get()));
$measure('ude_light', fn (): int => count(QueryBuilder::table($executor, 'bench_orders')->light()->get()));
$measure('ude_cursor', function () use ($executor): int {
    $count = 0;
    foreach (QueryBuilder::table($executor, 'bench_orders')->cursor() as $ignored) {
        $count++;
    }

    return $count;
});

$environment = [
    'php' => PHP_VERSION,
    'laravel' => class_exists(Application::class) ? Application::VERSION : 'illuminate/database standalone',
    'driver' => 'sqlite:memory',
    'server' => (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'dataset_rows' => $rows,
    'measured_at' => date('c'),
];

echo 'Benchmark Eloquent/QueryBuilder/UDE ('.$rows.' rows)'.PHP_EOL;
printf("%-20s %8s %12s %12s %10s\n", 'mode', 'rows', 'time(ms)', 'rows/sec', 'mem(KB)');
foreach ($results as $row) {
    printf("%-20s %8d %12.3f %12d %10.1f\n", $row['mode'], $row['rows'], $row['time_ms'], $row['rows_per_sec'], $row['memory_kb']);
}

if (isset($options['json']) || isset($options['markdown'])) {
    if (! is_dir($output)) {
        mkdir($output, 0775, true);
    }

    if (isset($options['json'])) {
        file_put_contents($output.'/laravel-bench-'.$rows.'.json', json_encode(['environment' => $environment, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    if (isset($options['markdown'])) {
        $lines = ['# Benchmark Eloquent vs UDE (SQLite)', '', 'Dataset: **'.$rows.' filas**', '', '| Mode | Rows | Time (ms) | Rows/sec | Mem (KB) |', '|---|---|---|---|---|'];
        foreach ($results as $row) {
            $lines[] = '| '.$row['mode'].' | '.$row['rows'].' | '.$row['time_ms'].' | '.$row['rows_per_sec'].' | '.$row['memory_kb'].' |';
        }
        file_put_contents($output.'/laravel-bench-'.$rows.'.md', implode(PHP_EOL, $lines).PHP_EOL);
    }
}

if ($assertFactor !== null) {
    $baseline = null;
    $ude = null;
    foreach ($results as $index => $row) {
        if ($row['mode'] === 'pdo_fetch_all') {
            $baseline = $index;
        }
        if ($row['mode'] === 'ude_light') {
            $ude = $index;
        }
    }

    if ($baseline === null || $ude === null || $results[$ude]['time_ms'] > $results[$baseline]['time_ms'] * $assertFactor) {
        fwrite(STDERR, 'Regression: UDE light outside the allowed factor versus raw PDO.'.PHP_EOL);
        exit(1);
    }

    echo sprintf("Assertion OK: UDE light within %.2fx of raw PDO.\n", $assertFactor);
}
