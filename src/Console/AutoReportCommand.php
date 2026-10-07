<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Auto\VanillaMonitor;

final class AutoReportCommand extends BaseCommand
{
    protected $signature = 'ude:auto:report {--json : Emitir JSON} {--markdown : Escribir reporte Markdown} {--reset : Limpiar tras reportar}';

    protected $description = 'Reporte del monitor automático: N+1, queries duplicadas, lentas y sugerencias';

    public function handle(): int
    {
        if (! $this->laravel->bound(VanillaMonitor::class)) {
            $this->components->warn('El monitor está deshabilitado (orm-universal.auto.mode=off).');

            return self::SUCCESS;
        }

        /** @var VanillaMonitor $monitor */
        $monitor = $this->laravel->make(VanillaMonitor::class);
        $report = $monitor->report();
        $paths = $this->writeReport('auto-report', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Metric', 'Value'], [
                ['Queries', (string) $report['queries']],
                ['Unique fingerprints', (string) $report['unique_fingerprints']],
                ['Duplicated patterns', (string) count($report['duplicates'])],
                ['Slow queries', (string) count($report['slow_queries'])],
            ]);

            foreach ($report['suggestions'] as $suggestion) {
                $this->components->warn($suggestion);
            }
        }

        if ($this->option('markdown')) {
            $this->components->info('Reporte: '.$paths['markdown']);
        }

        if ($this->option('reset')) {
            $monitor->reset();
            $this->components->info('Monitor reseteado.');
        }

        return self::SUCCESS;
    }
}
