<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Analysis\Codemod;

final class MigrateCodemodCommand extends BaseCommand
{
    protected $signature = 'ude:migrate:codemod {--diff : Mostrar diff unificado} {--backup : Recomendar backup/branch} {--interactive : Confirmar cada sugerencia} {--path=* : Rutas a analizar}';

    protected $description = 'Codemod conservador: sugiere conversiones sin modificar archivos (dry-run implícito)';

    public function handle(): int
    {
        $paths = $this->option('path') ?: [app_path(), database_path('migrations')];
        $codemod = new Codemod;
        $suggestions = $codemod->suggest($paths);

        if ($this->option('backup')) {
            $this->components->warn('Antes de aplicar cambios crea una rama/commit: git checkout -b ude-migration');
            $this->components->bulletList(['Nunca se modifica un archivo silenciosamente.']);
        }

        if ($suggestions === []) {
            $this->components->info('Sin sugerencias: el código ya está alineado con el motor.');

            return self::SUCCESS;
        }

        $rows = array_map(
            static fn (array $suggestion): array => [
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $suggestion['file']).':'.$suggestion['line'],
                $suggestion['severity'],
                $suggestion['suggestion'],
            ],
            $suggestions,
        );

        $this->table(['Location', 'Severity', 'Suggestion'], $rows);

        if ($this->option('diff')) {
            foreach (array_slice($suggestions, 0, 5) as $suggestion) {
                $this->newLine();
                $this->line('--- '.$suggestion['file'].':'.$suggestion['line']);
                $this->line('- '.$suggestion['current']);
                $this->line('+ '.$suggestion['suggestion']);
            }
        }

        $paths = $this->writeReport('migrate-codemod', ['suggestions' => $suggestions]);
        $this->components->info('Reporte: '.$paths['json'].' (dry-run: ningún archivo fue modificado)');

        return self::SUCCESS;
    }
}
