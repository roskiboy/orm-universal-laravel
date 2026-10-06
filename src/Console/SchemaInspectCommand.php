<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

final class SchemaInspectCommand extends BaseCommand
{
    protected $signature = 'ude:schema:inspect {table? : Tabla a inspeccionar} {--connection= : Conexión} {--json : Emitir JSON}';

    protected $description = 'Inspecciona tablas, columnas, índices y claves foráneas reales';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);

        if (($table = $this->argument('table')) === null) {
            $tables = $inspector->tables();

            if ($this->option('json')) {
                $this->line((string) json_encode(['tables' => $tables], JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $this->table(['Tables'], array_map(static fn (string $name): array => [$name], $tables));

            return self::SUCCESS;
        }

        $definition = $inspector->table(new TableIdentifier($table));

        if ($definition === null) {
            $this->components->error('Table not found: '.$table);

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($definition->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('Table: '.$definition->name);
        $this->table(['Column', 'Type', 'Nullable', 'Default', 'Auto', 'Generated'], array_map(
            static fn ($column): array => [
                $column->name,
                $column->databaseType,
                $column->nullable ? 'yes' : 'no',
                $column->default === null ? '' : (string) $column->default,
                $column->autoIncrement ? 'yes' : 'no',
                $column->generatedExpression ?? '',
            ],
            $definition->columns,
        ));

        $this->table(['Index', 'Columns', 'Unique', 'Primary', 'Type'], array_map(
            static fn ($index): array => [$index->name, implode(', ', $index->columns), $index->unique ? 'yes' : 'no', $index->primary ? 'yes' : 'no', $index->type->value],
            $definition->indexes,
        ));

        $this->table(['Foreign key', 'Columns', 'References', 'On delete', 'On update'], array_map(
            static fn ($key): array => [$key->name, implode(', ', $key->columns), $key->foreignTable.'('.implode(', ', $key->foreignColumns).')', (string) $key->onDelete, (string) $key->onUpdate],
            $definition->foreignKeys,
        ));

        return self::SUCCESS;
    }
}
