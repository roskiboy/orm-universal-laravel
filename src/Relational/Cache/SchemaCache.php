<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Cache;

use OrmUniversal\Contracts\ValueObjects\SchemaSnapshot;
use Psr\SimpleCache\CacheInterface;

/**
 * Schema metadata cache with explicit invalidation (migration, connection change
 * or manual clear) so stale table definitions are never served silently.
 *
 * @api public
 */
final class SchemaCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $namespace = 'ude',
        private readonly int $ttl = 3600,
    ) {}

    public function key(string $connection, string $dialect, string $schema = 'default'): string
    {
        return $this->namespace.':schema:'.$connection.':'.$dialect.':'.$schema;
    }

    /**
     * @param  callable(): SchemaSnapshot  $resolver
     */
    public function remember(string $key, callable $resolver, ?int $ttl = null): SchemaSnapshot
    {
        $cached = $this->cache->get($key);

        if ($cached instanceof SchemaSnapshot) {
            return $cached;
        }

        $snapshot = $resolver();
        $this->cache->set($key, $snapshot, $ttl ?? $this->ttl);

        return $snapshot;
    }

    public function forget(string $key): bool
    {
        return $this->cache->delete($key);
    }
}
