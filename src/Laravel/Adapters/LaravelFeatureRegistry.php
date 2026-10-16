<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Traits\Macroable;
use Laravel\Octane\Octane;

/**
 * Feature detection registry: the platform never branches on a Laravel version
 * number, it asks whether a class/method/behavior exists.
 *
 * @api public
 */
final class LaravelFeatureRegistry
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function supports(string $feature): bool
    {
        return $this->cache[$feature] ??= $this->detect($feature);
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        foreach ($this->knownFeatures() as $feature) {
            $this->supports($feature);
        }

        return $this->cache;
    }

    /**
     * @return list<string>
     */
    public function primaryFeatures(): array
    {
        return [
            'octane',
            'eloquent_builder_macros',
            'model_macroable',
            'query_builder_morph',
            'schema_blueprint_foreign_id',
            'connection_pdo_accessor',
            'pagination_cursor',
            'db_transaction_retry',
            'queue_serializes_models',
        ];
    }

    /**
     * @return list<string>
     */
    private function knownFeatures(): array
    {
        return array_merge($this->primaryFeatures(), array_keys($this->cache));
    }

    private function detect(string $feature): bool
    {
        return match ($feature) {
            'octane' => class_exists(Octane::class),
            'eloquent_builder_macros' => is_callable([Builder::class, 'macro']),
            'model_macroable' => trait_exists(Macroable::class),
            'query_builder_morph' => $this->hasMethod(\Illuminate\Database\Query\Builder::class, 'whereMorphedTo'),
            'schema_blueprint_foreign_id' => $this->hasMethod(Blueprint::class, 'foreignId'),
            'connection_pdo_accessor' => $this->hasMethod(Connection::class, 'getPdo'),
            'pagination_cursor' => $this->hasMethod(\Illuminate\Database\Query\Builder::class, 'cursorPaginate'),
            'db_transaction_retry' => $this->hasMethod(Connection::class, 'transaction'),
            'queue_serializes_models' => class_exists(Dispatcher::class),
            default => false,
        };
    }

    /**
     * Behavior detection through reflection: works for instance and static
     * methods without triggering static-analysis false positives.
     */
    private function hasMethod(string $class, string $method): bool
    {
        return class_exists($class) && (new \ReflectionClass($class))->hasMethod($method);
    }
}
