<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Driver;

use OrmUniversal\Contracts\Exceptions\DriverException;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;

/**
 * Redis driver (phpredis). Documents are stored as JSON strings with a per
 * collection id set. Backend filtering is not native: filters are evaluated with
 * the shared matcher, which is documented as a limitation.
 *
 * @api public
 */
final class RedisDriver implements DocumentDriverInterface
{
    private ?\Redis $redis = null;

    private readonly FilterMatcher $matcher;

    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
        private readonly string $prefix = 'ude',
        private readonly ?string $password = null,
        private readonly int $database = 0,
    ) {
        $this->matcher = new FilterMatcher;
    }

    public static function available(): bool
    {
        return extension_loaded('redis');
    }

    public function name(): string
    {
        return 'redis';
    }

    public function capabilities(): CapabilitySet
    {
        return CapabilitySet::of([
            Capability::NOSQL_KEY_VALUE,
            Capability::NOSQL_JSON_DOCUMENTS,
            Capability::NOSQL_TTL,
            Capability::NOSQL_PUBSUB,
            Capability::NOSQL_STREAMS,
            Capability::NOSQL_LUA,
            Capability::NOSQL_PERSISTENCE,
        ]);
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities()->supports($capability);
    }

    public function insertMany(string $collection, array $documents): array
    {
        $ids = [];
        $redis = $this->redis();

        foreach ($documents as $document) {
            $id = (string) ($document['_id'] ?? $document['id'] ?? uniqid('doc_', true));
            $document['_id'] = $id;
            $redis->set($this->documentKey($collection, $id), json_encode($document, JSON_THROW_ON_ERROR));
            $redis->sAdd($this->idSetKey($collection), $id);
            $ids[] = $id;
        }

        return $ids;
    }

    public function find(string $collection, array $filter = [], array $options = []): array
    {
        $documents = $this->all($collection);
        $matched = [];

        foreach ($documents as $document) {
            if ($this->matcher->matches($document, $filter)) {
                $matched[] = $document;
            }
        }

        return array_map(static fn (array $document): Document => Document::fromArray($document), $matched);
    }

    public function findOne(string $collection, array $filter = []): ?array
    {
        foreach ($this->all($collection) as $document) {
            if ($this->matcher->matches($document, $filter)) {
                return $document;
            }
        }

        return null;
    }

    public function updateMany(string $collection, array $filter, array $update): int
    {
        $redis = $this->redis();
        $updated = 0;

        foreach ($this->all($collection) as $document) {
            if (! $this->matcher->matches($document, $filter)) {
                continue;
            }

            $changes = isset($update['$set']) ? (array) $update['$set'] : $update;
            $merged = array_merge($document, $changes);
            $redis->set($this->documentKey($collection, (string) $document['_id']), json_encode($merged, JSON_THROW_ON_ERROR));
            $updated++;
        }

        return $updated;
    }

    public function deleteMany(string $collection, array $filter): int
    {
        $redis = $this->redis();
        $deleted = 0;

        foreach ($this->all($collection) as $document) {
            if (! $this->matcher->matches($document, $filter)) {
                continue;
            }

            $redis->del($this->documentKey($collection, (string) $document['_id']));
            $redis->sRem($this->idSetKey($collection), (string) $document['_id']);
            $deleted++;
        }

        return $deleted;
    }

    public function count(string $collection, array $filter = []): int
    {
        return count($this->find($collection, $filter));
    }

    public function aggregate(string $collection, Pipeline $pipeline): array
    {
        throw new UnsupportedFeatureException(
            feature: Capability::NOSQL_AGGREGATION,
            engine: 'redis',
            engineVersion: '5.0.14',
            reason: 'Redis is a key-value store: it has no aggregation pipeline',
            alternatives: ['Retrieve the documents and aggregate in the application layer', 'Use MongoDB when aggregation is required'],
        );
    }

    public function drop(string $collection): void
    {
        $redis = $this->redis();
        foreach ($this->all($collection) as $document) {
            $redis->del($this->documentKey($collection, (string) $document['_id']));
        }

        $redis->del($this->idSetKey($collection));
    }

    public function collections(): array
    {
        $names = [];
        $iterator = null;
        $redis = $this->redis();

        while (($keys = $redis->scan($iterator, $this->prefix.':col:*:ids', 100)) !== false) {
            foreach ($keys as $key) {
                $names[] = substr($key, strlen($this->prefix.':col:'), -4);
            }
            if ($iterator === 0) {
                break;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function all(string $collection): array
    {
        $redis = $this->redis();
        $ids = array_map('strval', (array) $redis->sMembers($this->idSetKey($collection)));

        if ($ids === []) {
            return [];
        }

        $raw = $redis->mGet(array_map(fn (string $id): string => $this->documentKey($collection, $id), $ids));

        $documents = [];
        foreach ($raw as $json) {
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $documents[] = $decoded;
                }
            }
        }

        return $documents;
    }

    private function documentKey(string $collection, string $id): string
    {
        return $this->prefix.':col:'.$collection.':doc:'.$id;
    }

    private function idSetKey(string $collection): string
    {
        return $this->prefix.':col:'.$collection.':ids';
    }

    private function redis(): \Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        if (! self::available()) {
            throw new DriverException('The Redis driver requires the phpredis extension (ext-redis).');
        }

        $redis = new \Redis;
        if (! $redis->connect($this->host, $this->port)) {
            throw new DriverException('Unable to connect to Redis at '.$this->host.':'.$this->port.'.');
        }

        if ($this->password !== null) {
            $redis->auth($this->password);
        }

        if ($this->database !== 0) {
            $redis->select($this->database);
        }

        return $this->redis = $redis;
    }
}
