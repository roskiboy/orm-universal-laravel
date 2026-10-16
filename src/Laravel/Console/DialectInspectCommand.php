<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

final class DialectInspectCommand extends BaseCommand
{
    protected $signature = 'ude:dialect:inspect {--connection= : Conexión a inspeccionar}';

    protected $description = 'Muestra dialecto activo, versión del servidor y capacidades negociadas';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $dialect = $executor->dialect();
        $capabilities = $dialect->capabilities()->all();

        $this->components->info('Dialecto: '.$dialect->name());
        $this->table(['Propiedad', 'Valor'], [
            ['Vendor', $dialect->vendor()->value],
            ['Family', $dialect->family()->value],
            ['Version', $dialect->version()->raw],
            ['Driver', $dialect->driverName()],
            ['Server', $executor->connection()->serverVersion()],
        ]);

        $rows = [];
        foreach ($capabilities as $capability => $value) {
            $rows[] = [$capability, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value];
        }

        $this->table(['Capability', 'Value'], $rows);

        $this->components->info('Limitaciones:');
        $this->components->bulletList($dialect->limitations() ?: ['(ninguna declarada)']);

        return self::SUCCESS;
    }
}
