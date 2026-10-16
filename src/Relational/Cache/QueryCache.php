<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Cache;

use OrmUniversal\Relational\Dialect\AbstractDialect;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache for normalized ASTs, compiled queries and prepared statement metadata.
 * Keys always include dialect + server version so cache entries never leak
 * across engines (cache safety rule).
 *
 * @api public
 */
final class QueryCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $namespace = 'ude',
        private readonly int $ttl = 3600,
    ) {}

    public function key(AbstractDialect $dialect, string $purpose, string $fingerprint): string
    {
        return $this->namespace.':'.$purpose.':'.$dialect->vendor()->value.':'.$dialect->version()->numeric().':'.sha1($fingerprint);
    }

    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->set($key, $value, $ttl ?? $this->ttl);
    }

    public function forget(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }
}
