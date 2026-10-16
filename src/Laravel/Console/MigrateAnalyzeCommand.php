<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MigrateAnalyzeCommand extends BaseCommand
{
    protected $signature = 'ude:migrate:analyze {--json : Emitir JSON}';

    protected $description = 'Clasifica migraciones: compatible, convertible, requiere revisión, no soportado, específico del motor';

    public function handle(): int
    {
        $directory = database_path('migrations');
        $classifications = [
            'compatible' => [],
            'convertible' => [],
            'needs_review' => [],
            'unsupported' => [],
            'vendor_specific' => [],
        ];

        foreach ($this->migrationFiles($directory) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $filePath = str_replace('\\', '/', $file->getPathname());

            if (preg_match('/DB::(unprepared|statement)\s*\(/', $contents) === 1) {
                $classifications['needs_review'][] = $filePath;
            } elseif (preg_match('/(->engine|->charset|->collation|fullText|->virtualAs|->storedAs|->jsonb|->vector)\s*\(/', $contents) === 1) {
                $classifications['vendor_specific'][] = $filePath;
            } elseif (preg_match('/(foreignId|foreign|dropForeign|renameColumn|dropColumn|change\s*\()/', $contents) === 1) {
                $classifications['convertible'][] = $filePath;
            } else {
                $classifications['compatible'][] = $filePath;
            }
        }

        $report = [
            'total' => count($classifications['compatible']) + count($classifications['convertible']) + count($classifications['needs_review']) + count($classifications['unsupported']) + count($classifications['vendor_specific']),
            'classifications' => $classifications,
        ];

        $paths = $this->writeReport('migrate-analyze', $report);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Classification', 'Count'], [
            ['Compatible', (string) count($classifications['compatible'])],
            ['Convertible', (string) count($classifications['convertible'])],
            ['Needs review', (string) count($classifications['needs_review'])],
            ['Unsupported', (string) count($classifications['unsupported'])],
            ['Vendor specific', (string) count($classifications['vendor_specific'])],
        ]);

        $this->components->info('Reporte: '.$paths['json']);

        return self::SUCCESS;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function migrationFiles(string $directory): iterable
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
