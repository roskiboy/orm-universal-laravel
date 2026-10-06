<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel;

use OrmUniversal\Laravel\Adapters\LaravelConnectionAdapter;
use OrmUniversal\Laravel\Adapters\LaravelFeatureRegistry;
use OrmUniversal\Laravel\Compatibility\LaravelCompatibilityLayer;
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use OrmUniversal\NoSql\Driver\MongoDbDriver;
use OrmUniversal\NoSql\Driver\RedisDriver;
use OrmUniversal\Relational\Business\BusinessRegistry;
use OrmUniversal\Relational\Context\ContextScope;
use OrmUniversal\Relational\Dialect\DialectRegistry;
use OrmUniversal\Relational\Observability\InMemoryProfiler;
use OrmUniversal\Relational\Optimizer\Optimizer;
use OrmUniversal\Relational\Type\TypeRegistry;

/**
 * Platform kernel exposed to the Laravel container and the OrmUniversal facade:
 * registries, optimizer, profiler, contexts and NoSQL store.
 *
 * @api public
 */
final class Ude
{
    public function __construct(
        private readonly DialectRegistry $dialects = new DialectRegistry,
        private readonly TypeRegistry $types = new TypeRegistry,
        private readonly BusinessRegistry $business = new BusinessRegistry,
        private readonly ContextScope $contexts = new ContextScope,
        private readonly InMemoryProfiler $profiler = new InMemoryProfiler,
        private readonly Optimizer $optimizer = new Optimizer,
        private readonly DocumentStore $nosql = new DocumentStore,
        private readonly LaravelFeatureRegistry $features = new LaravelFeatureRegistry,
        private readonly LaravelCompatibilityLayer $compatibility = new LaravelCompatibilityLayer,
        private readonly LaravelConnectionAdapter $connections = new LaravelConnectionAdapter,
    ) {}

    public static function create(): self
    {
        return new self(
            dialects: DialectRegistry::withDefaults(),
            types: TypeRegistry::withDefaults(),
        );
    }

    public function dialects(): DialectRegistry
    {
        return $this->dialects;
    }

    public function types(): TypeRegistry
    {
        return $this->types;
    }

    public function business(): BusinessRegistry
    {
        return $this->business;
    }

    public function contexts(): ContextScope
    {
        return $this->contexts;
    }

    public function profiler(): InMemoryProfiler
    {
        return $this->profiler;
    }

    public function optimizer(): Optimizer
    {
        return $this->optimizer;
    }

    public function nosql(): DocumentStore
    {
        return $this->nosql;
    }

    public function features(): LaravelFeatureRegistry
    {
        return $this->features;
    }

    public function compatibility(): LaravelCompatibilityLayer
    {
        return $this->compatibility;
    }

    public function connections(): LaravelConnectionAdapter
    {
        return $this->connections;
    }

    /**
     * Register the NoSQL drivers described in the configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function bootNoSql(array $config): void
    {
        $drivers = $config['drivers'] ?? [];

        if (($drivers['in_memory']['enabled'] ?? false) === true && ! in_array('in-memory', $this->nosql->drivers(), true)) {
            $this->nosql->register('in-memory', new InMemoryDriver, (bool) ($config['default'] === 'in-memory'));
        }

        if (($drivers['mongodb']['enabled'] ?? false) === true && MongoDbDriver::available()) {
            $this->nosql->register('mongodb', new MongoDbDriver(
                uri: (string) ($drivers['mongodb']['uri'] ?? 'mongodb://127.0.0.1:27017'),
                database: (string) ($drivers['mongodb']['database'] ?? 'ude'),
            ), (bool) (($config['default'] ?? null) === 'mongodb'));
        }

        if (($drivers['redis']['enabled'] ?? false) === true && RedisDriver::available()) {
            $this->nosql->register('redis', new RedisDriver(
                host: (string) ($drivers['redis']['host'] ?? '127.0.0.1'),
                port: (int) ($drivers['redis']['port'] ?? 6379),
                prefix: (string) ($drivers['redis']['prefix'] ?? 'ude'),
            ), (bool) (($config['default'] ?? null) === 'redis'));
        }
    }

    /**
     * Reset per-request state. Mandatory under Octane, Swoole, RoadRunner,
     * queues and long-running workers.
     */
    public function flush(): void
    {
        $this->contexts->flush();
        $this->profiler->reset();
    }
}
