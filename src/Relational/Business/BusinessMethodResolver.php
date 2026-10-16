<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use OrmUniversal\Contracts\Exceptions\BusinessOperationException;

/**
 * Resolves magic business methods (paid, overdue, forCustomer, riskGreaterThan)
 * against the registry. Resolution order is explicit and every decision is
 * recorded for debugging; ambiguity throws instead of guessing.
 *
 * @api public
 */
final class BusinessMethodResolver
{
    private ?string $lastTrace = null;

    public function __construct(
        private readonly BusinessRegistry $registry,
        private readonly MethodCache $cache = new MethodCache,
    ) {}

    public function cache(): MethodCache
    {
        return $this->cache;
    }

    public function lastTrace(): ?string
    {
        return $this->lastTrace;
    }

    public function supportsScope(string $method, ?string $model = null): bool
    {
        return (bool) $this->cache->remember(
            'scope:'.($model ?? '*').':'.$method,
            fn (): bool => $this->registry->hasScope($method, $model),
        );
    }

    public function supportsOperation(string $method, ?string $model = null): bool
    {
        return (bool) $this->cache->remember(
            'operation:'.($model ?? '*').':'.$method,
            fn (): bool => $this->registry->hasOperation($method, $model),
        );
    }

    public function resolveScope(string $method, ?string $model = null): BusinessScope
    {
        return $this->cache->remember('scope-object:'.($model ?? '*').':'.$method, function () use ($method, $model): BusinessScope {
            try {
                $definition = $this->registry->scope($method, $model);
            } catch (BusinessOperationException $exception) {
                $this->lastTrace = 'scope:'.$method.' -> unresolved';

                throw $exception;
            }

            $this->lastTrace = 'scope:'.$method.' -> '.(is_string($definition) ? $definition : 'closure');

            return new BusinessScope($method, $definition, $model);
        });
    }

    public function resolveOperation(string $method, ?string $model = null): BusinessOperation
    {
        return $this->cache->remember('operation-object:'.($model ?? '*').':'.$method, function () use ($method, $model): BusinessOperation {
            try {
                $handler = $this->registry->operation($method, $model);
            } catch (BusinessOperationException $exception) {
                $this->lastTrace = 'operation:'.$method.' -> unresolved';

                throw $exception;
            }

            $this->lastTrace = 'operation:'.$method.' -> '.(is_string($handler) ? $handler : 'closure');

            return new BusinessOperation($method, $handler, $model);
        });
    }

    /**
     * Apply the first resolvable business method in the documented order:
     * registered scope, then business operation.
     *
     * @param  list<mixed>  $arguments
     */
    public function apply(string $method, object $query, ?string $model = null, array $arguments = []): bool
    {
        if ($this->supportsScope($method, $model)) {
            $this->resolveScope($method, $model)->apply($query);

            return true;
        }

        if ($this->supportsOperation($method, $model)) {
            $this->resolveOperation($method, $model)->handle($query, ...$arguments);

            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'scopes' => count($this->registry->scopes()),
            'operations' => count($this->registry->operations()),
            'cache' => $this->cache->stats(),
            'last_trace' => $this->lastTrace,
        ];
    }
}
