<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

/**
 * Detects uncommitted changes for a path using git, when available.
 *
 * @api public
 */
final class GitDirtyGuard
{
    public static function isDirty(string $path): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        $output = @shell_exec('git status --porcelain -- '.escapeshellarg($path).' 2>&1');

        return is_string($output) && trim($output) !== '';
    }
}
