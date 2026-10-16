<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

final class CacheClearCommand extends BaseCommand
{
    protected $signature = 'ude:cache:clear {--connection= : Conexión específica}';

    protected $description = 'Limpia caches de la plataforma: metadatos, conexiones, profiler y contexto';

    public function handle(): int
    {
        $kernel = $this->kernel();
        $kernel->profiler()->reset();
        $kernel->contexts()->flush();
        $kernel->connections()->flush($this->option('connection'));

        $this->components->info('Caches UDE limpiadas.');
        $this->components->bulletList([
            'profiler: reset',
            'context scope: flush',
            'connection adapters: '.($this->option('connection') ?? 'all'),
        ]);

        return self::SUCCESS;
    }
}
