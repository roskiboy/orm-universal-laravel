<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Conservative codemod: it never rewrites files silently. It produces
 * suggestions with file/line/severity and optional unified diffs so the developer
 * applies them under version control.
 *
 * @api public
 */
final class Codemod
{
    /**
     * @param  list<string>  $paths
     * @return list<array{file: string, line: int, severity: string, suggestion: string, current: string}>
     */
    public function suggest(array $paths): array
    {
        $suggestions = [];

        $rules = [
            '/DB::table\((.+?)\)->get\(\)/' => ['likely_safe', 'Construir el query con OrmUniversal::table(...) para habilitar recordset/streaming.'],
            '/->get\(\)/' => ['safe', 'Considerar recordset()/cursor() para datasets grandes.'],
            '/::all\(\)/' => ['likely_safe', 'Reemplazar all() por streaming (cursor) cuando la tabla crece.'],
            '/collection\(\$rows\)->map/' => ['safe', 'El recordset soporta map/filter sin materializar de nuevo.'],
            '/DB::select\(/' => ['requires_review', 'Revisar SQL crudo: envolver en SqlFragment::parameterized.'],
            '/DB::unprepared\(/' => ['requires_review', 'SQL crudo no parametrizado: convertir a migración/DDL o revisar manualmente.'],
        ];

        foreach ($this->phpFiles($paths) as $file) {
            $lines = file($file->getPathname()) ?: [];

            foreach ($lines as $number => $line) {
                foreach ($rules as $pattern => [$severity, $suggestion]) {
                    if (preg_match($pattern, $line) === 1) {
                        $suggestions[] = [
                            'file' => $file->getPathname(),
                            'line' => $number + 1,
                            'severity' => $severity,
                            'suggestion' => $suggestion,
                            'current' => trim($line),
                        ];
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Unified diff preview between the current content and a proposed replacement.
     */
    public function diff(string $current, string $proposed): string
    {
        $from = explode("\n", $current);
        $to = explode("\n", $proposed);
        $diff = "--- current\n+++ proposed\n";

        $max = max(count($from), count($to));
        for ($index = 0; $index < $max; $index++) {
            $a = $from[$index] ?? null;
            $b = $to[$index] ?? null;

            if ($a === $b) {
                $diff .= ' '.$a."\n";

                continue;
            }

            if ($a !== null) {
                $diff .= '-'.$a."\n";
            }

            if ($b !== null) {
                $diff .= '+'.$b."\n";
            }
        }

        return $diff;
    }

    /**
     * @param  list<string>  $paths
     * @return iterable<SplFileInfo>
     */
    private function phpFiles(array $paths): iterable
    {
        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    yield $file;
                }
            }
        }
    }
}
