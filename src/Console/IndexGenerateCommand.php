<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Laravel\Analysis\IndexAdvisor;
use OrmUniversal\Laravel\Analysis\IndexMigrationGenerator;
use OrmUniversal\Laravel\Analysis\ProfilerIndexAdvisor;
use OrmUniversal\Laravel\Auto\VanillaMonitor;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

/**
 * Generates a Laravel migration from index recommendations. Dry-run by default;
 * writes only with `--write` and never overwrites without `--force`.
 *
 * @api public
 */
final class IndexGenerateCommand extends BaseCommand
{
    protected $signature = 'ude:index:generate
        {table? : Tabla a analizar (por defecto todas)}
        {--connection= : Conexión}
        {--from-profiler : Usa las muestras del monitor automático}
        {--path= : Directorio de migraciones (por defecto database/migrations)}
        {--write : Escribe el archivo de migración}
        {--force : Sobrescribe si el archivo ya existe}
        {--json : Emitir JSON}';

    protected $description = 'Genera una migración con los índices recomendados (dry-run por defecto, nunca toca datos)';

    public function handle(): int
    {
        $advice = (bool) $this->option('from-profiler') ? $this->profilerAdvice() : $this->schemaAdvice();

        if ($advice === []) {
            $this->writeReport('index-generate', ['recommendations' => []]);
            $this->components->info('Sin recomendaciones de índices.');

            return self::SUCCESS;
        }

        $generator = new IndexMigrationGenerator;
        $filename = $generator->filename(date('Y_m_d_His'));
        $directory = (string) ($this->option('path') ?: database_path('migrations'));
        $target = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$filename;
        $content = $generator->generate($advice);

        $this->table(['Table', 'Column', 'Reason'], array_map(
            static fn (array $item): array => [$item['table'], $item['column'], $item['reason']],
            $advice,
        ));

        if (! (bool) $this->option('write')) {
            $report = $this->writeReport('index-generate', [
                'mode' => 'dry-run',
                'file' => $target,
                'recommendations' => $advice,
            ]);
            $this->components->warn('Dry-run: no se escribió ninguna migración. Usa --write.');
            $this->components->info('Reporte: '.$report['markdown']);

            return self::SUCCESS;
        }

        if (is_file($target) && ! (bool) $this->option('force')) {
            $this->components->error("El archivo [{$target}] ya existe; usa --force.");

            return self::FAILURE;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($target, $content);
        $this->components->info('Migración generada: '.$target);
        $this->components->warn('Revisa el archivo y ejecuta `php artisan migrate` en ventana de baja carga.');

        $this->writeReport('index-generate', [
            'mode' => 'write',
            'file' => $target,
            'recommendations' => $advice,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<array{table: string, column: string, reason: string, suggestion: string}>
     */
    private function schemaAdvice(): array
    {
        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);
        $tables = $this->argument('table') !== null ? [(string) $this->argument('table')] : $inspector->tables();
        $advisor = new IndexAdvisor;
        $advice = [];

        foreach ($tables as $table) {
            $definition = $inspector->table(new TableIdentifier($table));
            if ($definition === null) {
                continue;
            }

            $advice = array_merge($advice, $advisor->adviseTable($table, $definition));
        }

        return $advice;
    }

    /**
     * @return list<array{table: string, column: string, reason: string, suggestion: string}>
     */
    private function profilerAdvice(): array
    {
        if (! $this->laravel->bound(VanillaMonitor::class)) {
            $this->components->error('El monitor automático está desactivado (orm-universal.auto.mode=off).');

            return [];
        }

        $monitor = $this->laravel->make(VanillaMonitor::class);
        $samples = [];

        foreach ($monitor->duplicates() as $group) {
            $samples[] = (string) ($group['sample'] ?? '');
        }

        foreach ($monitor->slowQueries() as $query) {
            $samples[] = (string) ($query['sql'] ?? '');
        }

        return (new ProfilerIndexAdvisor)->suggest($samples);
    }
}
