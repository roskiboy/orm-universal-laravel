<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Analysis\Codemod;
use OrmUniversal\Laravel\Analysis\ConfigDatabaseCodemod;
use OrmUniversal\Laravel\Analysis\GitDirtyGuard;

/**
 * Converts connection drivers in `config/database.php` to the `ude-*` drivers
 * with a surgical AST-based codemod. Dry-run by default; the file is only
 * written with an explicit `--write`.
 *
 * @api public
 */
final class AutoEnableCommand extends BaseCommand
{
    protected $signature = 'ude:auto:enable
        {--connection=* : Conexiones a convertir (por defecto todas las elegibles)}
        {--path= : Ruta de config/database.php (por defecto config_path)}
        {--dry-run : Solo muestra los cambios (comportamiento por defecto)}
        {--diff : Muestra el diff unificado}
        {--backup : Crea una copia de seguridad antes de escribir}
        {--interactive : Pide confirmación por conexión}
        {--write : Escribe los cambios (sin esta opción nunca modifica el archivo)}
        {--force : Permite escribir aunque el archivo tenga cambios sin commitear}';

    protected $description = 'Convierte conexiones de config/database.php a los drivers ude-* (dry-run por defecto)';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: config_path('database.php'));

        if (! is_file($path)) {
            $this->components->error("No existe el archivo [{$path}].");

            return self::FAILURE;
        }

        $code = (string) file_get_contents($path);
        $codemod = new ConfigDatabaseCodemod;
        $edits = $codemod->analyze($code);

        $selected = array_values(array_filter((array) $this->option('connection'), static fn ($value): bool => is_string($value) && $value !== ''));

        if ($selected !== []) {
            $edits = array_values(array_filter($edits, static fn (array $edit): bool => in_array($edit['connection'], $selected, true)));
        }

        if ($this->option('interactive')) {
            $edits = array_values(array_filter($edits, function (array $edit): bool {
                return $this->confirm("Convertir [{$edit['connection']}]: {$edit['current']} → {$edit['proposed']}?", true);
            }));
        }

        if ($edits === []) {
            $this->components->info('No hay conexiones elegibles que convertir.');

            return self::SUCCESS;
        }

        $this->table(
            ['Conexión', 'Driver actual', 'Driver propuesto'],
            array_map(static fn (array $edit): array => [$edit['connection'], $edit['current'], $edit['proposed']], $edits),
        );

        $proposed = $codemod->apply($code, $edits);

        if ($this->option('diff')) {
            $this->line((new Codemod)->diff($code, $proposed));
        }

        if (! (bool) $this->option('write')) {
            $report = $this->writeReport('auto-enable', [
                'mode' => 'dry-run',
                'path' => $path,
                'connections' => array_map(static fn (array $edit): string => $edit['connection'], $edits),
            ]);

            $this->components->warn('Dry-run: no se modificó ningún archivo. Usa --write --backup (y una rama/commit previo).');
            $this->components->info('Reporte: '.$report['markdown']);

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && GitDirtyGuard::isDirty($path)) {
            $this->components->error('El archivo tiene cambios sin commitear. Crea una rama/commit o usa --force.');

            return self::FAILURE;
        }

        if ($this->option('backup')) {
            $backup = $path.'.bak-'.date('YmdHis');

            if (! copy($path, $backup)) {
                $this->components->error("No se pudo crear la copia de seguridad [{$backup}].");

                return self::FAILURE;
            }

            $this->components->info('Backup: '.$backup);
        }

        if (file_put_contents($path, $proposed) === false) {
            $this->components->error("No se pudo escribir [{$path}].");

            return self::FAILURE;
        }

        $report = $this->writeReport('auto-enable', [
            'mode' => 'write',
            'path' => $path,
            'connections' => array_map(static fn (array $edit): string => $edit['connection'], $edits),
        ]);

        $this->components->info('Config actualizado: '.$path);
        $this->components->info('Reporte: '.$report['markdown']);
        $this->components->warn('Revisa el diff, reinicia workers/Octane y ejecuta la suite de paridad.');

        return self::SUCCESS;
    }
}
