<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Analysis\ModelInspector;
use OrmUniversal\Laravel\Analysis\ProjectAnalyzer;

final class ModelInspectCommand extends BaseCommand
{
    protected $signature = 'ude:model:inspect {model? : Clase o namespace parcial} {--json : Emitir JSON}';

    protected $description = 'Analiza modelos: relaciones, scopes, casts, accessors/mutators y observers';

    public function handle(): int
    {
        $inspector = new ModelInspector;
        $argument = $this->argument('model');

        $classes = $argument !== null
            ? [$this->resolveClass((string) $argument)]
            : array_keys((new ProjectAnalyzer(base_path(), (array) config('orm-universal.tooling.model_paths', ['app/Models'])))->models());

        $report = [];
        foreach ($classes as $class) {
            $report[] = $inspector->inspect($class);
        }

        $paths = $this->writeReport('models', ['models' => $report]);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($report as $model) {
                $this->components->info((string) ($model['class'] ?? '?'));
                $this->table(['Property', 'Value'], [
                    ['Table', (string) ($model['table'] ?? '-')],
                    ['Connection', (string) ($model['connection'] ?? 'default')],
                    ['Relations', (string) count($model['relations'] ?? [])],
                    ['Scopes', implode(', ', $model['scopes'] ?? [])],
                    ['Casts', (string) count($model['casts'] ?? [])],
                    ['Soft deletes', ($model['soft_deletes'] ?? false) ? 'yes' : 'no'],
                ]);
            }
            $this->components->info('Reporte: '.$paths['json']);
        }

        return self::SUCCESS;
    }

    private function resolveClass(string $argument): string
    {
        if (class_exists($argument)) {
            return $argument;
        }

        $candidate = rtrim((string) config('orm-universal.tooling.model_namespace', 'App\\Models'), '\\').'\\'.$argument;

        return class_exists($candidate) ? $candidate : $argument;
    }
}
