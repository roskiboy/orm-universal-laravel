<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Relational\Dialect\DialectRegistry;

final class DialectListCommand extends BaseCommand
{
    protected $signature = 'ude:dialect:list';

    protected $description = 'Lista los dialectos registrados y su línea de versión por defecto';

    public function handle(): int
    {
        /** @var DialectRegistry $registry */
        $registry = $this->kernel()->dialects();
        $rows = [];

        foreach (DialectVendor::cases() as $vendor) {
            if (! $registry->has($vendor)) {
                continue;
            }

            $dialect = $registry->for($vendor->defaultVersion());
            $rows[] = [
                $vendor->value,
                $vendor->family()->value,
                $dialect->version()->raw,
                $dialect->driverName(),
                count($dialect->capabilities()->all()),
            ];
        }

        $this->table(['Vendor', 'Family', 'Default version', 'PDO driver', 'Capabilities'], $rows);

        return self::SUCCESS;
    }
}
