<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Laravel\Adapters\LaravelModelAdapter;
use OrmUniversal\Laravel\Analysis\Codemod;
use OrmUniversal\Laravel\Analysis\GitDirtyGuard;
use OrmUniversal\Laravel\Analysis\ModelCastCodemod;
use OrmUniversal\Laravel\Analysis\TypeMapAnalyzer;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Compares real database columns with model `$casts`, suggests corrections and
 * can write them into the model with a surgical php-parser codemod. Dry-run by
 * default; never writes without `--write`.
 *
 * @api public
 */
final class TypesMapCommand extends BaseCommand
{
    protected $signature = 'ude:types:map
        {model? : Modelo (FQCN o nombre corto); sin argumento escanea tooling.model_paths}
        {--connection= : Conexión}
        {--dry-run : Solo muestra las sugerencias (comportamiento por defecto)}
        {--diff : Muestra el diff unificado del modelo}
        {--backup : Copia de seguridad antes de escribir}
        {--write : Escribe las correcciones (requiere modelos con propiedad $casts)}
        {--force : Permite escribir con el modelo modificado en git}
        {--json : Emitir JSON}';

    protected $description = 'Compara columnas reales con $casts y sugiere/corrige casts (decimales siempre string)';

    public function handle(): int
    {
        $models = $this->models();

        if ($models === []) {
            $this->components->error('No se encontraron modelos para analizar.');

            return self::FAILURE;
        }

        $adapter = new LaravelModelAdapter;
        $analyzer = new TypeMapAnalyzer;
        $codemod = new ModelCastCodemod;
        $results = [];
        $written = [];

        foreach ($models as $class) {
            $metadata = $adapter->metadata($class);
            $executor = $this->executor($this->option('connection') ?: (string) ($metadata['connection'] ?? ''));
            $inspector = SchemaInspectorFactory::for($executor->dialect(), $executor);
            $definition = $inspector->table(new TableIdentifier((string) $metadata['table']));

            if ($definition === null) {
                continue;
            }

            $suggestions = $analyzer->analyze($definition->columns, (array) $metadata['casts']);

            if ($suggestions === []) {
                continue;
            }

            $file = (new ReflectionClass($class))->getFileName();
            $entry = [
                'model' => $class,
                'table' => (string) $metadata['table'],
                'file' => is_string($file) ? $file : null,
                'suggestions' => $suggestions,
                'writable' => false,
            ];

            if (is_string($file) && is_file($file)) {
                $code = (string) file_get_contents($file);
                $changes = array_map(
                    static fn (array $suggestion): array => ['column' => $suggestion['column'], 'suggested' => $suggestion['suggested']],
                    $suggestions,
                );
                $proposed = $codemod->apply($code, $changes);
                $entry['writable'] = $proposed !== null;

                if ($proposed !== null) {
                    if ($this->option('diff')) {
                        $this->line((new Codemod)->diff($code, $proposed));
                    }

                    if ((bool) $this->option('write')) {
                        if (! (bool) $this->option('force') && GitDirtyGuard::isDirty($file)) {
                            $this->components->error("El modelo [{$class}] tiene cambios sin commitear; usa --force.");

                            return self::FAILURE;
                        }

                        if ($this->option('backup')) {
                            copy($file, $file.'.bak-'.date('YmdHis'));
                        }

                        file_put_contents($file, $proposed);
                        $written[] = $file;
                    }
                }
            }

            $results[] = $entry;
        }

        $report = [
            'models_analyzed' => count($models),
            'models_with_suggestions' => count($results),
            'written' => $written,
            'results' => $results,
        ];

        $this->writeReport('types-map', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($results === []) {
            $this->components->info('Sin sugerencias: los casts declarados coinciden con las columnas reales.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($results as $entry) {
            foreach ($entry['suggestions'] as $suggestion) {
                $rows[] = [
                    basename((string) $entry['file']),
                    $entry['table'],
                    $suggestion['column'],
                    $suggestion['database_type'],
                    var_export($suggestion['current'], true),
                    $suggestion['suggested'],
                    $suggestion['severity'],
                ];
            }
        }

        $this->table(['Model', 'Table', 'Column', 'DB type', 'Current', 'Suggested', 'Severity'], $rows);

        if (! (bool) $this->option('write')) {
            $this->components->warn('Dry-run: no se modificó ningún modelo. Usa --write --backup tras revisar el diff.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<class-string>
     */
    private function models(): array
    {
        $argument = $this->argument('model');

        if (is_string($argument) && $argument !== '') {
            if (class_exists($argument)) {
                return [$argument];
            }

            $namespace = (string) config('orm-universal.tooling.model_namespace', 'App\\Models');
            $candidate = $namespace.'\\'.$argument;

            return class_exists($candidate) ? [$candidate] : [];
        }

        $classes = [];
        $paths = (array) config('orm-universal.tooling.model_paths', [app_path('Models')]);

        foreach ($paths as $path) {
            if (! is_string($path) || ! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFromFile((string) $file->getRealPath());

                if ($class !== null) {
                    $classes[] = $class;
                }
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return class-string|null
     */
    private function classFromFile(string $file): ?string
    {
        $code = (string) file_get_contents($file);
        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($code);

        if ($ast === null) {
            return null;
        }

        $namespace = '';
        foreach ($ast as $statement) {
            if ($statement instanceof Namespace_) {
                $namespace = $statement->name !== null ? $statement->name->toString().'\\' : '';

                foreach ($statement->stmts as $inner) {
                    if ($inner instanceof Class_ && $inner->name !== null) {
                        return $namespace.$inner->name->toString();
                    }
                }
            }

            if ($statement instanceof Class_ && $statement->name !== null) {
                return $statement->name->toString();
            }
        }

        return null;
    }
}
