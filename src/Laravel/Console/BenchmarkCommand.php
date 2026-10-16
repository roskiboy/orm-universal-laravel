<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use Illuminate\Support\Facades\DB;
use OrmUniversal\Relational\Builder\QueryBuilder;

final class BenchmarkCommand extends BaseCommand
{
    protected $signature = 'ude:benchmark
        {table : Tabla}
        {--rows=1000 : Filas a leer}
        {--connection= : Conexión}
        {--json : Escribir reporte JSON}
        {--markdown : Escribir reporte Markdown}
        {--assert-vs-pdo= : Fallar si UDE zero supera este factor sobre PDO fetch_all}';

    protected $description = 'Benchmark reproducible: PDO vs Laravel Query Builder vs UDE (zero/light/cursor)';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $table = (string) $this->argument('table');
        $limit = max(1, (int) $this->option('rows'));
        $pdo = $executor->connection()->rawPdo();

        $results = [];
        $measure = function (string $label, callable $operation) use (&$results, $limit): void {
            $memory = memory_get_usage();
            $started = microtime(true);
            $rows = $operation();
            $elapsed = (microtime(true) - $started) * 1000;

            $results[] = [
                'mode' => $label,
                'rows' => is_int($rows) ? $rows : $limit,
                'time_ms' => round($elapsed, 3),
                'rows_per_sec' => $elapsed > 0 ? (int) round(($limit / $elapsed) * 1000) : 0,
                'memory_kb' => round((memory_get_usage() - $memory) / 1024, 1),
            ];
        };

        if ($pdo !== null) {
            $measure('pdo_fetch_all', function () use ($pdo, $table, $limit): int {
                $statement = $pdo->query('select * from '.$table.' limit '.$limit);

                return count($statement->fetchAll(\PDO::FETCH_ASSOC));
            });
        }

        $measure('laravel_query_builder', fn (): int => DB::connection($this->option('connection'))
            ->table($table)->limit($limit)->get()->count());

        $measure('ude_zero', fn (): int => count(QueryBuilder::table($executor, $table)->limit($limit)->get()));
        $measure('ude_light', fn (): int => count(QueryBuilder::table($executor, $table)->limit($limit)->light()->get()));
        $measure('ude_cursor', function () use ($executor, $table, $limit): int {
            $count = 0;
            foreach (QueryBuilder::table($executor, $table)->limit($limit)->cursor() as $ignored) {
                $count++;
            }

            return $count;
        });

        $environment = [
            'table' => $table,
            'rows' => $limit,
            'php' => PHP_VERSION,
            'laravel' => $this->laravel->version(),
            'driver' => $executor->connection()->driverName(),
            'server' => $executor->connection()->serverVersion(),
            'dialect' => $executor->dialect()->name(),
            'measured_at' => date('c'),
        ];

        $this->table(['Mode', 'Rows', 'Time (ms)', 'Rows/sec', 'Mem (KB)'], array_map(
            static fn (array $row): array => [$row['mode'], $row['rows'], $row['time_ms'], $row['rows_per_sec'], $row['memory_kb']],
            $results,
        ));

        $report = ['environment' => $environment, 'results' => $results];
        if ($this->option('json')) {
            file_put_contents($this->reportDirectory().'/benchmark.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        if ($this->option('markdown')) {
            $lines = ['# Benchmark '.$environment['dialect'], '', 'Tabla `'.$table.'` · '.$limit.' filas', '', '| Mode | Rows | Time (ms) | Rows/sec | Mem (KB) |', '|---|---|---|---|---|'];
            foreach ($results as $row) {
                $lines[] = '| '.$row['mode'].' | '.$row['rows'].' | '.$row['time_ms'].' | '.$row['rows_per_sec'].' | '.$row['memory_kb'].' |';
            }
            file_put_contents($this->reportDirectory().'/benchmark.md', implode(PHP_EOL, $lines).PHP_EOL);
        }

        if ($this->option('json') || $this->option('markdown')) {
            $this->components->info('Reporte: '.$this->reportDirectory());
        }

        $factor = $this->option('assert-vs-pdo');
        if ($factor !== null) {
            $baseline = null;
            $ude = null;
            foreach ($results as $index => $row) {
                if ($row['mode'] === 'pdo_fetch_all') {
                    $baseline = $index;
                }
                if ($row['mode'] === 'ude_zero') {
                    $ude = $index;
                }
            }

            if ($baseline === null || $ude === null) {
                $this->components->warn('No se pudo evaluar el umbral (falta baseline PDO).');

                return self::SUCCESS;
            }

            if ($results[$ude]['time_ms'] > $results[$baseline]['time_ms'] * (float) $factor) {
                $this->components->error(sprintf('Regresión: UDE zero %.3f ms vs PDO %.3f ms (factor %.2f).', $results[$ude]['time_ms'], $results[$baseline]['time_ms'], (float) $factor));

                return self::FAILURE;
            }

            $this->components->info(sprintf('Umbral OK: UDE zero dentro de %.2fx de PDO.', (float) $factor));
        }

        return self::SUCCESS;
    }
}
