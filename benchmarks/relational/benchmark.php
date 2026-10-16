<?php

declare(strict_types=1);

/**
 * Reproducible engine benchmark: raw PDO vs UDE (zero/light/cursor/count).
 *
 * Usage:
 *   php benchmarks/benchmark.php --rows=10000
 *   php benchmarks/benchmark.php --driver=mysql --rows=50000 --json --markdown
 *   php benchmarks/benchmark.php --rows=10000 --assert-vs-pdo=2.0
 *
 * SQLite runs in memory by default. MySQL/PostgreSQL use UDE_MYSQL_* / UDE_PGSQL_*.
 */
$autoload = __DIR__.'/../../vendor/autoload.php';
if (! is_file($autoload)) {
    $autoload = __DIR__.'/../../../../vendor/autoload.php';
}
require $autoload;

use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;

$options = getopt('', ['driver::', 'rows::', 'json', 'markdown', 'assert-vs-pdo::', 'output::']);

$driver = (string) ($options['driver'] ?? 'sqlite');
$rows = max(100, (int) ($options['rows'] ?? 10000));
$assertFactor = isset($options['assert-vs-pdo']) ? (float) $options['assert-vs-pdo'] : null;
$output = (string) ($options['output'] ?? __DIR__.'/results');

[$pdo, $dsnLabel] = connect($driver);

$serverVersion = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

if ($driver === 'sqlite') {
    $serverVersion = (string) $pdo->query('select sqlite_version()')->fetchColumn();
}

$pdoDriver = $driver === 'pgsql' ? 'pgsql' : ($driver === 'mysql' ? 'mysql' : 'sqlite');
$dialect = DialectResolver::resolve($pdoDriver, $serverVersion);
$connection = new PdoConnection($pdo, 'bench-'.$driver);
$executor = new QueryExecutor($connection, $dialect);

$pdo->exec('drop table if exists bench_users');
$pdo->exec('create table bench_users (id integer primary key, name varchar(100) not null, email varchar(150) not null, age integer not null, active integer not null)');

$insert = $pdo->prepare('insert into bench_users (id, name, email, age, active) values (?, ?, ?, ?, ?)');
$pdo->beginTransaction();
for ($i = 1; $i <= $rows; $i++) {
    $insert->execute([$i, 'user'.$i, 'user'.$i.'@example.test', 18 + ($i % 60), ($i % 4 === 0) ? 0 : 1]);
    if ($i % 1000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

$results = [];
$measure = static function (string $label, callable $operation) use (&$results, $rows): void {
    $memoryBefore = memory_get_usage();
    $started = microtime(true);
    $count = $operation();
    $elapsed = (microtime(true) - $started) * 1000;

    $results[] = [
        'mode' => $label,
        'rows' => is_int($count) ? $count : $rows,
        'time_ms' => round($elapsed, 3),
        'rows_per_sec' => $elapsed > 0 ? (int) round(($rows / $elapsed) * 1000) : 0,
        'memory_kb' => round((memory_get_usage() - $memoryBefore) / 1024, 1),
    ];
};

$measure('pdo_fetch_all', function () use ($pdo): int {
    return count($pdo->query('select id, name, email, age, active from bench_users')->fetchAll(PDO::FETCH_ASSOC));
});

$measure('pdo_row_loop', function () use ($pdo): int {
    $statement = $pdo->query('select id, name, email, age, active from bench_users');
    $count = 0;
    while ($statement->fetch(PDO::FETCH_ASSOC) !== false) {
        $count++;
    }

    return $count;
});

$measure('ude_zero', fn (): int => count(QueryBuilder::table($executor, 'bench_users')->get()));
$measure('ude_light', fn (): int => count(QueryBuilder::table($executor, 'bench_users')->light()->get()));
$measure('ude_cursor', function () use ($executor): int {
    $count = 0;
    foreach (QueryBuilder::table($executor, 'bench_users')->cursor() as $ignored) {
        $count++;
    }

    return $count;
});
$measure('ude_count', fn (): int => QueryBuilder::table($executor, 'bench_users')->count());
$measure('ude_pluck_age', fn (): int => count(QueryBuilder::table($executor, 'bench_users')->pluck('age')));

$environment = [
    'php' => PHP_VERSION,
    'driver' => $dsnLabel,
    'server' => $serverVersion,
    'dialect' => $dialect->name(),
    'dataset_rows' => $rows,
    'measured_at' => date('c'),
];

render($environment, $results);

if (isset($options['json']) || isset($options['markdown'])) {
    if (! is_dir($output)) {
        mkdir($output, 0775, true);
    }

    if (isset($options['json'])) {
        file_put_contents($output.'/bench-'.$driver.'-'.$rows.'.json', json_encode(['environment' => $environment, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    if (isset($options['markdown'])) {
        $lines = ['# Benchmark '.$dialect->name(), '', 'Dataset: **'.$rows.' rows** · PHP '.PHP_VERSION.' · server '.$serverVersion, '', '| Mode | Rows | Time (ms) | Rows/sec | Mem (KB) |', '|---|---|---|---|---|'];
        foreach ($results as $row) {
            $lines[] = '| '.$row['mode'].' | '.$row['rows'].' | '.$row['time_ms'].' | '.$row['rows_per_sec'].' | '.$row['memory_kb'].' |';
        }
        file_put_contents($output.'/bench-'.$driver.'-'.$rows.'.md', implode(PHP_EOL, $lines).PHP_EOL);
    }
}

if ($assertFactor !== null) {
    $baseline = indexOf($results, 'pdo_fetch_all');
    $ude = indexOf($results, 'ude_zero');

    if ($baseline === null || $ude === null) {
        fwrite(STDERR, "Assertion failed: missing benchmark rows.\n");
        exit(1);
    }

    if ($results[$ude]['time_ms'] > $results[$baseline]['time_ms'] * $assertFactor) {
        fwrite(STDERR, sprintf(
            "Regression: UDE zero (%.3f ms) is slower than %.2fx raw PDO (%.3f ms).\n",
            $results[$ude]['time_ms'],
            $assertFactor,
            $results[$baseline]['time_ms'],
        ));
        exit(1);
    }

    echo sprintf("Assertion OK: UDE zero within %.2fx of raw PDO fetch_all.\n", $assertFactor);
}

/**
 * @return array{0: PDO, 1: string}
 */
function connect(string $driver): array
{
    return match ($driver) {
        'sqlite' => [new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), 'sqlite:memory'],
        'mysql' => (function (): array {
            $host = getenv('UDE_MYSQL_HOST') ?: (getenv('UDE_MARIADB_HOST') ?: '127.0.0.1');
            $port = getenv('UDE_MYSQL_PORT') ?: (getenv('UDE_MARIADB_PORT') ?: '3306');
            $database = getenv('UDE_MYSQL_DATABASE') ?: (getenv('UDE_MARIADB_DATABASE') ?: 'tms_ude_test');
            $user = getenv('UDE_MYSQL_USERNAME') ?: (getenv('UDE_MARIADB_USERNAME') ?: 'root');
            $password = getenv('UDE_MYSQL_PASSWORD') ?: (getenv('UDE_MARIADB_PASSWORD') ?: '');

            return [new PDO("mysql:host={$host};port={$port};dbname={$database}", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), "mysql:{$host}:{$port}/{$database}"];
        })(),
        'pgsql' => (function (): array {
            $host = getenv('UDE_PGSQL_HOST') ?: '127.0.0.1';
            $port = getenv('UDE_PGSQL_PORT') ?: '5432';
            $database = getenv('UDE_PGSQL_DATABASE') ?: 'tms_ude_test';
            $user = getenv('UDE_PGSQL_USERNAME') ?: 'postgres';
            $password = getenv('UDE_PGSQL_PASSWORD') ?: 'secret';

            return [new PDO("pgsql:host={$host};port={$port};dbname={$database}", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), "pgsql:{$host}:{$port}/{$database}"];
        })(),
        default => throw new InvalidArgumentException("Unsupported driver [{$driver}]. Use sqlite, mysql or pgsql."),
    };
}

/**
 * @param  array<string, mixed>  $environment
 * @param  list<array<string, mixed>>  $results
 */
function render(array $environment, array $results): void
{
    echo 'Benchmark '.$environment['dialect'].' @ '.$environment['server'].' ('.$environment['dataset_rows'].' rows)'.PHP_EOL;
    printf("%-18s %10s %12s %14s %10s\n", 'mode', 'rows', 'time(ms)', 'rows/sec', 'mem(KB)');
    foreach ($results as $row) {
        printf("%-18s %10d %12.3f %14d %10.1f\n", $row['mode'], $row['rows'], $row['time_ms'], $row['rows_per_sec'], $row['memory_kb']);
    }
}

/**
 * @param  list<array<string, mixed>>  $results
 */
function indexOf(array $results, string $mode): ?int
{
    foreach ($results as $index => $row) {
        if ($row['mode'] === $mode) {
            return $index;
        }
    }

    return null;
}
