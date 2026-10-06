<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

final class DoctorCommand extends BaseCommand
{
    protected $signature = 'ude:doctor {--connection= : Conexión a inspeccionar}';

    protected $description = 'Diagnostica entorno, extensiones, conexión, dialecto y capacidades';

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?? (string) config('database.default');
        $driver = (string) config('database.connections.'.$connectionName.'.driver', 'unknown');

        $checks = [
            'PHP' => PHP_VERSION,
            'Laravel' => $this->laravel->version(),
            'Package' => '0.1.0',
            'Connection' => $connectionName,
            'Driver' => $driver,
            'PDO drivers' => implode(', ', \PDO::getAvailableDrivers()),
            'ext-mongodb' => extension_loaded('mongodb') ? 'loaded' : 'missing',
            'ext-redis' => extension_loaded('redis') ? 'loaded' : 'missing',
            'Compatibility mode' => (string) config('orm-universal.compatibility_mode', 'hybrid'),
        ];

        try {
            $executor = $this->executor($connectionName);
            $serverVersion = $executor->connection()->serverVersion();
            $dialect = $executor->dialect();

            $checks['Server version'] = $serverVersion;
            $checks['Dialect'] = $dialect->name();
            $checks['Capabilities'] = (string) count($dialect->capabilities()->all());
            $checks['Ping'] = $executor->connection()->ping() ? 'ok' : 'failed';

            $this->table(['Check', 'Value'], array_map(
                static fn (string $key, string $value): array => [$key, $value],
                array_keys($checks),
                array_values($checks),
            ));

            $this->newLine();
            $this->components->info('Limitaciones declaradas del dialecto:');
            $this->components->bulletList($dialect->limitations() ?: ['(ninguna declarada)']);
        } catch (\Throwable $exception) {
            $this->table(['Check', 'Value'], array_map(
                static fn (string $key, string $value): array => [$key, $value],
                array_keys($checks),
                array_values($checks),
            ));
            $this->components->error('No se pudo conectar: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
