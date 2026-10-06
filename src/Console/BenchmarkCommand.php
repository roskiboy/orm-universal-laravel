<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use Illuminate\Support\Facades\DB;
use OrmUniversal\Relational\Builder\QueryBuilder;

final class BenchmarkCommand extends BaseCommand
{
    protected $signature = 'ude:benchmark {table : Tabla} {--rows=1000 : Filas a leer} {--connection= : Conexión}';

    protected $description = 'Benchmark reproducible: Laravel Query Builder vs UDE (zero/light/Eloquent)';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $table = (string) $this->argument('table');
        $limit = max(1, (int) $this->option('rows'));

        $results = [];

        $start = microtime(true);
        $memory = memory_get_usage();
        $count = DB::connection($this->option('connection'))->table($table)->limit($limit)->get()->count();
        $results[] = ['Laravel DB::table()->get()', $count, round((microtime(true) - $start) * 1000, 2), memory_get_usage() - $memory];

        foreach (['zero' => 'zero (array)', 'light' => 'light (stdClass)'] as $mode => $label) {
            $builder = QueryBuilder::table($executor, $table)->limit($limit);
            if ($mode === 'light') {
                $builder->light();
            }

            $start = microtime(true);
            $memory = memory_get_usage();
            $records = $builder->get();
            $results[] = ['UDE '.$label, count($records), round((microtime(true) - $start) * 1000, 2), memory_get_usage() - $memory];
        }

        $start = microtime(true);
        $memory = memory_get_usage();
        $streamed = 0;
        foreach (QueryBuilder::table($executor, $table)->limit($limit)->cursor() as $ignored) {
            $streamed++;
        }
        $results[] = ['UDE cursor (streaming)', $streamed, round((microtime(true) - $start) * 1000, 2), memory_get_usage() - $memory];

        $this->table(['Mode', 'Rows', 'Time (ms)', 'Memory delta (bytes)'], $results);

        $this->writeReport('benchmark', [
            'table' => $table,
            'rows' => $limit,
            'environment' => [
                'php' => PHP_VERSION,
                'laravel' => $this->laravel->version(),
                'driver' => $executor->connection()->driverName(),
                'server' => $executor->connection()->serverVersion(),
                'dialect' => $executor->dialect()->name(),
            ],
            'results' => $results,
        ]);

        $this->components->info('Reporte: '.$this->reportDirectory().'/benchmark.json');

        return self::SUCCESS;
    }
}
