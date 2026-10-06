<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

final class IndexAdviseCommand extends BaseCommand
{
    protected $signature = 'ude:index:advise {table? : Tabla a analizar} {--connection= : Conexión} {--json : Emitir JSON}';

    protected $description = 'Recomienda índices a partir del esquema real (solo recomendaciones, nunca crea índices)';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);

        $tables = $this->argument('table') !== null ? [(string) $this->argument('table')] : $inspector->tables();

        if ($tables === []) {
            $this->components->warn('No hay tablas para analizar.');

            return self::SUCCESS;
        }

        $advice = [];
        foreach ($tables as $table) {
            $definition = $inspector->table(new TableIdentifier($table));
            if ($definition === null) {
                continue;
            }

            $coveredFirst = [];
            $indexed = [];
            foreach ($definition->indexes as $index) {
                $first = strtolower($index->columns[0] ?? '');
                if ($first !== '') {
                    $coveredFirst[$first] = true;
                }
                foreach ($index->columns as $column) {
                    $indexed[strtolower($column)] = true;
                }
            }

            foreach ($definition->foreignKeys as $foreignKey) {
                foreach ($foreignKey->columns as $column) {
                    if (! isset($coveredFirst[strtolower($column)])) {
                        $advice[] = [
                            'table' => $table,
                            'column' => $column,
                            'reason' => 'Foreign key column not covered by an index prefix (constraint '.$foreignKey->name.').',
                            'suggestion' => 'CREATE INDEX '.$table.'_'.$column.'_idx ON '.$table.' ('.$column.');',
                        ];
                    }
                }
            }

            foreach ($definition->columns as $column) {
                $name = strtolower($column->name);
                if (str_ends_with($name, '_id') && ! isset($indexed[$name])) {
                    $advice[] = [
                        'table' => $table,
                        'column' => $column->name,
                        'reason' => 'Column looks like a foreign key but has no index.',
                        'suggestion' => 'CREATE INDEX '.$table.'_'.$column->name.'_idx ON '.$table.' ('.$column->name.');',
                    ];
                }
            }
        }

        $report = ['tables_analyzed' => count($tables), 'recommendations' => $advice];
        $this->writeReport('index-advice', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($advice === []) {
            $this->components->info('Sin recomendaciones: las claves foráneas detectadas ya están cubiertas.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Column', 'Reason'], array_map(
            static fn (array $item): array => [$item['table'], $item['column'], $item['reason']],
            $advice,
        ));

        $this->components->warn('Recomendaciones únicamente: revisa y aplica manualmente con tus migraciones.');

        return self::SUCCESS;
    }
}
