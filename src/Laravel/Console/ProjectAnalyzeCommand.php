<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Analysis\ProjectAnalyzer;

final class ProjectAnalyzeCommand extends BaseCommand
{
    protected $signature = 'ude:project:analyze {--json : Emitir JSON} {--path= : Ruta base del proyecto}';

    protected $description = 'Analiza modelos, queries, DB:: calls, SQL crudo, migraciones y servicios';

    public function handle(): int
    {
        $analyzer = new ProjectAnalyzer(
            $this->option('path') ?? base_path(),
            (array) config('orm-universal.tooling.model_paths', ['app/Models']),
        );

        $report = $analyzer->analyze();
        $paths = $this->writeReport('project-analyze', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(['Metric', 'Value'], [
            ['Models', (string) $report['models']],
            ['Migrations', (string) $report['migrations']],
            ['Files scanned', (string) $report['files_scanned']],
            ['Raw SQL sites', (string) $report['raw_sql']],
        ]);

        $counts = [];
        foreach ($report['counts'] as $key => $count) {
            if ($count > 0) {
                $counts[] = [$key, (string) $count];
            }
        }
        $this->table(['Pattern', 'Occurrences'], $counts);

        $this->components->info('Reportes:');
        $this->components->bulletList([
            $paths['json'],
            $paths['markdown'],
        ]);

        return self::SUCCESS;
    }
}
