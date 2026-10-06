<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Console;

use Illuminate\Console\Command;
use OrmUniversal\Laravel\Ude;
use OrmUniversal\Relational\Execution\QueryExecutor;

/**
 * Shared helpers for UDE artisan commands: kernel access, executors and report
 * writing (JSON + Markdown) under storage/ude.
 *
 * @api public
 */
abstract class BaseCommand extends Command
{
    protected function kernel(): Ude
    {
        return $this->laravel->make(Ude::class);
    }

    protected function executor(?string $connection = null): QueryExecutor
    {
        return $this->kernel()->connections()->executor(
            $connection ?? (string) config('database.default'),
            $this->kernel()->profiler(),
        );
    }

    protected function reportDirectory(): string
    {
        $directory = (string) config('orm-universal.tooling.report_path', storage_path('ude'));

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{json: string, markdown: string}
     */
    protected function writeReport(string $name, array $payload): array
    {
        $directory = $this->reportDirectory();
        $jsonPath = $directory.'/'.$name.'.json';
        $markdownPath = $directory.'/'.$name.'.md';

        file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $lines = ['# UDE '.$name, '', 'Generado: '.date('c'), ''];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $lines[] = '- **'.$key.'**: '.var_export($value, true);
            }
        }

        file_put_contents($markdownPath, implode(PHP_EOL, $lines).PHP_EOL);

        return ['json' => $jsonPath, 'markdown' => $markdownPath];
    }
}
