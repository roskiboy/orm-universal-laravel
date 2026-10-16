<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Cache;

use OrmUniversal\Contracts\TransactionInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use Psr\SimpleCache\CacheInterface;

/**
 * Opt-in query result cache. It bypasses the cache while a transaction is active
 * and includes connection, dialect, tenant, SQL and bindings in the key so stale
 * or cross-tenant results are never served.
 *
 * @api public
 */
final class ResultCache
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $namespace = 'ude',
        private readonly int $ttl = 60,
        private readonly ?TransactionInterface $transaction = null,
    ) {}

    public function enabled(): bool
    {
        return $this->transaction === null || ! $this->transaction->isActive();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function key(AbstractDialect $dialect, string $connection, ?string $tenant, CompiledQuery $query, array $parameters = []): string
    {
        $payload = $query->sql.'|'.serialize($query->bindings).'|'.serialize($parameters);

        return $this->namespace.':result:'.$dialect->vendor()->value.':'.$dialect->version()->numeric()
            .':'.$connection.':'.($tenant ?? '-').':'.sha1($payload);
    }

    /**
     * @param  callable(): mixed  $resolver
     */
    public function remember(string $key, callable $resolver, ?int $ttl = null): mixed
    {
        if (! $this->enabled()) {
            return $resolver();
        }

        $cached = $this->cache->get($key, self::MISS);

        if ($cached !== self::MISS) {
            return $cached;
        }

        $value = $resolver();
        $this->cache->set($key, $value, $ttl ?? $this->ttl);

        return $value;
    }

    public function forget(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return ['enabled' => $this->enabled(), 'ttl' => $this->ttl, 'namespace' => $this->namespace];
    }

    private const MISS = "\0ude-cache-miss\0";
}
