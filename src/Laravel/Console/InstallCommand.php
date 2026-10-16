<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

final class InstallCommand extends BaseCommand
{
    protected $signature = 'ude:install {--force : Sobrescribir la configuración existente}';

    protected $description = 'Instala la plataforma OrmUniversal: publica configuración y verifica el entorno';

    public function handle(): int
    {
        $this->components->info('OrnUniversal install');

        $this->call('vendor:publish', [
            '--tag' => 'orm-universal-config',
            ...($this->option('force') ? ['--force' => true] : []),
        ]);

        $this->call('ude:doctor');

        $this->newLine();
        $this->components->info('Siguiente paso: php artisan ude:project:analyze');
        $this->components->bulletList([
            'Modo híbrido activo: Eloquent sigue funcionando sin cambios.',
            'Revisa config/orm-universal.php para ajustar modo, NoSQL y tooling.',
            'Reportes en storage/ude/.',
        ]);

        return self::SUCCESS;
    }
}
