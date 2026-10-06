<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

final class CompatibilityCommand extends BaseCommand
{
    protected $signature = 'ude:compatibility {--json : Emitir JSON} {--connection= : Conexión a inspeccionar}';

    protected $description = 'Reporte de compatibilidad: PHP, Laravel, paquete, base de datos y driver';

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?? (string) config('database.default');
        $driver = (string) config('database.connections.'.$connectionName.'.driver', 'unknown');
        $report = [
            'php' => PHP_VERSION,
            'laravel' => $this->laravel->version(),
            'package' => 'orm-universal/relational 0.1.0',
            'connection' => $connectionName,
            'driver' => $driver,
            'compatibility_mode' => (string) config('orm-universal.compatibility_mode', 'hybrid'),
            'features' => $this->kernel()->features()->all(),
        ];

        try {
            $executor = $this->executor($connectionName);
            $report['server_version'] = $executor->connection()->serverVersion();
            $report['dialect'] = $executor->dialect()->name();
            $report['supported'] = true;
            $report['warnings'] = $executor->dialect()->limitations();
        } catch (\Throwable $exception) {
            $report['supported'] = false;
            $report['error'] = $exception->getMessage();
            $report['warnings'] = [];
        }

        $paths = $this->writeReport('compatibility', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Item', 'Value'], [
                ['PHP', $report['php']],
                ['Laravel', $report['laravel']],
                ['Package', $report['package']],
                ['Driver', $report['driver']],
                ['Dialect', $report['dialect'] ?? '-'],
                ['Server', $report['server_version'] ?? '-'],
                ['Supported', $report['supported'] ? 'yes' : 'no'],
                ['Warnings', (string) count($report['warnings'])],
            ]);
            $this->components->info('Reporte: '.$paths['markdown']);
        }

        return ($report['supported'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
