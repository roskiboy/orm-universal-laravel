<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Laravel\Analysis\IndexAdvisor;
use OrmUniversal\Laravel\Analysis\ProfilerIndexAdvisor;
use OrmUniversal\Laravel\Auto\VanillaMonitor;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

final class IndexAdviseCommand extends BaseCommand
{
    protected $signature = 'ude:index:advise {table? : Tabla a analizar} {--connection= : Conexión} {--json : Emitir JSON} {--from-profiler : Analizar las muestras del monitor automático en lugar del esquema}';

    protected $description = 'Recomienda índices a partir del esquema real o del profiler (solo recomendaciones, nunca crea índices)';

    public function handle(): int
    {
        if ((bool) $this->option('from-profiler')) {
            return $this->fromProfiler();
        }

        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);

        $tables = $this->argument('table') !== null ? [(string) $this->argument('table')] : $inspector->tables();

        if ($tables === []) {
            $this->components->warn('No hay tablas para analizar.');

            return self::SUCCESS;
        }

        $advisor = new IndexAdvisor;
        $advice = [];
        foreach ($tables as $table) {
            $definition = $inspector->table(new TableIdentifier($table));
            if ($definition === null) {
                continue;
            }

            $advice = array_merge($advice, $advisor->adviseTable($table, $definition));
        }

        $report = ['tables_analyzed' => count($tables), 'recommendations' => $advice];
        $this->writeReport('index-advice', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($advice === []) {
            $this->components->info('Sin recomendaciones: las claves foráneas detectadas ya están cubiertas.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Column', 'Reason'], array_map(
            static fn (array $item): array => [$item['table'], $item['column'], $item['reason']],
            $advice,
        ));

        $this->components->warn('Recomendaciones únicamente: revisa y aplica manualmente con tus migraciones.');

        return self::SUCCESS;
    }

    private function fromProfiler(): int
    {
        if (! $this->laravel->bound(VanillaMonitor::class)) {
            $this->components->error('El monitor automático está desactivado (orm-universal.auto.mode=off).');

            return self::FAILURE;
        }

        $monitor = $this->laravel->make(VanillaMonitor::class);
        $samples = [];

        foreach ($monitor->duplicates() as $group) {
            $samples[] = (string) ($group['sample'] ?? '');
        }

        foreach ($monitor->slowQueries() as $query) {
            $samples[] = (string) ($query['sql'] ?? '');
        }

        $advice = (new ProfilerIndexAdvisor)->suggest($samples);
        $report = [
            'source' => 'profiler',
            'samples' => count(array_filter($samples)),
            'recommendations' => $advice,
        ];

        $this->writeReport('index-advice-profiler', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($advice === []) {
            $this->components->info('Sin recomendaciones a partir de las muestras del profiler.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Column', 'Reason'], array_map(
            static fn (array $item): array => [$item['table'], $item['column'], $item['reason']],
            $advice,
        ));

        $this->components->warn('Recomendaciones únicamente: revisa y aplica manualmente con tus migraciones.');

        return self::SUCCESS;
    }
}
