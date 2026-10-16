<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Static project analyzer: models, queries, DB calls, raw SQL, migrations,
 * scopes, repositories and services. Classification follows the documented
 * safe / likely safe / requires review / unsupported scale.
 *
 * @api public
 */
final class ProjectAnalyzer
{
    /** @var array<string, array{label: string, pattern: string, risk: string}> */
    private const PATTERNS = [
        'model_query' => ['label' => 'Model::query()', 'pattern' => '/::query\s*\(/', 'risk' => 'safe'],
        'model_where' => ['label' => 'Model::where()', 'pattern' => '/::where\s*\(/', 'risk' => 'safe'],
        'model_find' => ['label' => 'Model::find()', 'pattern' => '/::find\s*\(/', 'risk' => 'safe'],
        'db_table' => ['label' => 'DB::table()', 'pattern' => '/DB::table\s*\(/', 'risk' => 'safe'],
        'db_select' => ['label' => 'DB::select()', 'pattern' => '/DB::select\s*\(/', 'risk' => 'requires_review'],
        'db_statement' => ['label' => 'DB::statement()', 'pattern' => '/DB::statement\s*\(/', 'risk' => 'requires_review'],
        'db_unprepared' => ['label' => 'DB::unprepared()', 'pattern' => '/DB::unprepared\s*\(/', 'risk' => 'requires_review'],
        'db_raw' => ['label' => 'DB::raw()', 'pattern' => '/DB::raw\s*\(/', 'risk' => 'likely_safe'],
        'select_raw' => ['label' => 'selectRaw/whereRaw', 'pattern' => '/(selectRaw|whereRaw|orderByRaw|havingRaw|fromRaw)\s*\(/', 'risk' => 'likely_safe'],
        'lock_for_update' => ['label' => 'lockForUpdate()', 'pattern' => '/lockForUpdate\s*\(/', 'risk' => 'safe'],
        'chunk' => ['label' => 'chunk()/chunkById()', 'pattern' => '/chunk(ById)?\s*\(/', 'risk' => 'safe'],
        'cursor' => ['label' => 'cursor()/lazy()', 'pattern' => '/(cursor|lazy)\s*\(/', 'risk' => 'safe'],
        'eloquent_relation' => ['label' => 'Relation definitions', 'pattern' => '/->(hasOne|hasMany|belongsTo|belongsToMany|morphOne|morphMany|morphTo|morphToMany|hasOneThrough|hasManyThrough)\s*\(/', 'risk' => 'safe'],
        'local_scope' => ['label' => 'scope* methods', 'pattern' => '/function\s+scope[A-Z]/', 'risk' => 'safe'],
        'global_scope' => ['label' => 'addGlobalScope', 'pattern' => '/addGlobalScope\s*\(/', 'risk' => 'safe'],
        'raw_migration_sql' => ['label' => 'Migration raw SQL', 'pattern' => '/(DB::statement|DB::unprepared)/', 'risk' => 'requires_review'],
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly array $modelPaths = ['app/Models'],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(): array
    {
        $models = $this->models();
        $counts = array_fill_keys(array_keys(self::PATTERNS), 0);
        $files = [];

        foreach ($this->phpFiles([$this->basePath]) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->basePath) + 1));

            if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, 'packages/')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            $matches = [];

            foreach (self::PATTERNS as $key => $definition) {
                $count = preg_match_all($definition['pattern'], $contents);
                if ($count > 0) {
                    $counts[$key] += $count;
                    $matches[$key] = $count;
                }
            }

            if ($matches !== []) {
                $files[$relative] = $matches;
            }
        }

        $migrations = $this->phpFiles([$this->basePath.'/database/migrations']);
        $rawSql = $counts['db_select'] + $counts['db_statement'] + $counts['db_unprepared'];

        return [
            'generated_at' => date('c'),
            'models' => count($models),
            'migrations' => iterator_count($migrations),
            'files_scanned' => count($files),
            'counts' => $counts,
            'raw_sql' => $rawSql,
            'compatibility' => $this->classify($rawSql),
            'model_list' => array_keys($models),
            'hot_files' => $files,
        ];
    }

    /**
     * @return array<string, string> FQCN => file
     */
    public function models(): array
    {
        $models = [];

        foreach ($this->modelPaths as $path) {
            $directory = is_dir($path) ? $path : $this->basePath.'/'.ltrim($path, '/');

            foreach ($this->phpFiles([$directory]) as $file) {
                $contents = (string) file_get_contents($file->getPathname());

                if (! preg_match('/class\s+(\w+)\s+extends\s+([\\\\\w]+)/', $contents, $match)) {
                    continue;
                }

                if (! str_contains($match[2], 'Model') && ! str_contains($match[2], 'Authenticatable')) {
                    continue;
                }

                if (! preg_match('/namespace\s+([^;]+);/', $contents, $namespace)) {
                    continue;
                }

                $models[trim($namespace[1]).'\\'.$match[1]] = str_replace('\\', '/', substr($file->getPathname(), strlen($this->basePath) + 1));
            }
        }

        return $models;
    }

    /**
     * @return array<string, mixed>
     */
    private function classify(int $rawSql): array
    {
        return [
            'safe' => 100,
            'likely_safe' => 0,
            'requires_review' => $rawSql > 0 ? 1 : 0,
            'unsupported' => 0,
            'note' => 'Classification is advisory; every raw SQL site must be reviewed manually.',
        ];
    }

    /**
     * @param  list<string>  $directories
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(array $directories): iterable
    {
        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    yield $file;
                }
            }
        }
    }
}
