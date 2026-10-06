<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Laravel\Analysis\ModelInspector;
use OrmUniversal\Laravel\Analysis\ProjectAnalyzer;
use OrmUniversal\Relational\Relation\RelationResolver;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

final class MapCommand extends BaseCommand
{
    protected $signature = 'ude:map {--connection= : Conexión} {--json : Emitir JSON}';

    protected $description = 'Mapa del proyecto: base de datos -> tablas -> modelos -> relaciones -> queries';

    public function handle(): int
    {
        $executor = $this->executor($this->option('connection'));
        $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);
        $snapshot = $inspector->snapshot();

        $analyzer = new ProjectAnalyzer(base_path(), (array) config('orm-universal.tooling.model_paths', ['app/Models']));
        $project = $analyzer->analyze();
        $modelInspector = new ModelInspector;
        $resolver = new RelationResolver;

        $map = [
            'database' => [
                'connection' => $executor->connection()->driverName(),
                'dialect' => $executor->dialect()->name(),
                'tables' => count($snapshot->tables),
            ],
            'tables' => [],
            'models' => [],
        ];

        foreach ($snapshot->tables as $name => $table) {
            $map['tables'][$name] = [
                'columns' => count($table->columns),
                'indexes' => count($table->indexes),
                'foreign_keys' => count($table->foreignKeys),
                'inferred_belongs_to' => array_map(
                    static fn ($relation): string => $relation->name().' -> '.$relation->relatedTable()->name,
                    $resolver->belongsToMany($name, $table->foreignKeys),
                ),
            ];
        }

        foreach ($analyzer->models() as $class => $file) {
            $inspection = $modelInspector->inspect($class);
            $map['models'][$class] = [
                'file' => $file,
                'table' => $inspection['table'] ?? null,
                'relations' => $inspection['relations'] ?? [],
                'scopes' => $inspection['scopes'] ?? [],
            ];
        }

        $map['queries'] = $project['counts'];

        $paths = $this->writeReport('project-map', $map);

        if ($this->option('json')) {
            $this->line((string) json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Layer', 'Count'], [
                ['Tables', (string) count($map['tables'])],
                ['Models', (string) count($map['models'])],
                ['Migrations', (string) $project['migrations']],
                ['Raw SQL sites', (string) $project['raw_sql']],
            ]);
            $this->components->info('Mapa: '.$paths['json']);
        }

        return self::SUCCESS;
    }
}
