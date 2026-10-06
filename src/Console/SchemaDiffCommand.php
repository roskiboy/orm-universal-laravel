<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\SchemaSnapshot;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Relational\Schema\SchemaDiffer;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

final class SchemaDiffCommand extends BaseCommand
{
    protected $signature = 'ude:schema:diff {--connection= : Conexión} {--snapshot= : Snapshot JSON deseado} {--json : Emitir JSON}';

    protected $description = 'Compara el esquema actual contra un snapshot deseado';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);
        $current = $inspector->snapshot();

        $snapshotPath = $this->option('snapshot');
        if ($snapshotPath === null) {
            $payload = [
                'tables' => count($current->tables),
                'dialect' => $current->dialect,
                'server_version' => $current->serverVersion,
                'note' => 'Pasa --snapshot=archivo.json con el esquema deseado para calcular el diff.',
            ];

            $paths = $this->writeReport('schema-snapshot', $payload);

            if ($this->option('json')) {
                $this->line((string) json_encode($payload, JSON_PRETTY_PRINT));
            } else {
                $this->table(['Tables', 'Dialect', 'Server'], [[(string) count($current->tables), (string) $current->dialect, (string) $current->serverVersion]]);
                $this->components->info('Snapshot actual: '.$paths['json']);
            }

            return self::SUCCESS;
        }

        if (! is_file($snapshotPath)) {
            $this->components->error('Snapshot not found: '.$snapshotPath);

            return self::FAILURE;
        }

        $desired = $this->loadSnapshot($snapshotPath);
        $diff = (new SchemaDiffer)->diff($current, $desired);

        $paths = $this->writeReport('schema-diff', $diff);

        if ($this->option('json')) {
            $this->line((string) json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Change', 'Count'], [
            ['Tables to create', (string) count($diff['tables_to_create'])],
            ['Tables to drop', (string) count($diff['tables_to_drop'])],
            ['Tables changed', (string) count($diff['tables_changed'])],
        ]);
        $this->components->info('Diff: '.$paths['json']);

        return self::SUCCESS;
    }

    private function loadSnapshot(string $path): SchemaSnapshot
    {
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $tables = [];

        foreach (($payload['tables'] ?? []) as $name => $table) {
            $columns = [];
            foreach (($table['columns'] ?? []) as $column) {
                $columns[] = new ColumnDefinition(
                    name: (string) $column['name'],
                    databaseType: (string) ($column['database_type'] ?? ''),
                    kind: TypeKind::tryFrom((string) ($column['kind'] ?? 'custom')) ?? TypeKind::Custom,
                    nullable: (bool) ($column['nullable'] ?? true),
                    default: $column['default'] ?? null,
                );
            }

            $tables[$name] = new TableDefinition(name: (string) ($table['name'] ?? $name), columns: $columns);
        }

        return new SchemaSnapshot(tables: $tables, dialect: $payload['dialect'] ?? null, serverVersion: $payload['server_version'] ?? null);
    }
}
