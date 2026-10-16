<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Cache;

use OrmUniversal\Contracts\ValueObjects\CompiledQuery;

/**
 * Process-level memo for compiled plans, in front of any persistent (Redis,
 * APCu, …) plan cache.
 *
 * Measured on tms (PHP 8.5, OPcache CLI): compiling a small AST costs ~18 µs,
 * while a Redis plan lookup costs ~70 µs (roundtrip + unserialize). The memory
 * layer removes the remote roundtrip for repeated shapes and makes the cache
 * a net win for heavy shapes without penalizing light ones.
 *
 * The store is deterministic (key = dialect + server version + fingerprint) and
 * immutable, so it is safe to keep across Octane requests in the same worker.
 * It is bounded: on overflow the oldest quarter of entries is dropped.
 *
 * @api public
 */
final class CompiledPlanMemory
{
    public const LIMIT = 512;

    /** @var array<string, CompiledQuery> */
    private static array $plans = [];

    public static function get(string $key): ?CompiledQuery
    {
        return self::$plans[$key] ?? null;
    }

    public static function put(string $key, CompiledQuery $plan): void
    {
        if (! isset(self::$plans[$key]) && count(self::$plans) >= self::LIMIT) {
            self::$plans = array_slice(self::$plans, intdiv(self::LIMIT, 4), null, true);
        }

        self::$plans[$key] = $plan;
    }

    public static function count(): int
    {
        return count(self::$plans);
    }

    public static function flush(): void
    {
        self::$plans = [];
    }
}
