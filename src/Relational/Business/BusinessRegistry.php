<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use Closure;
use OrmUniversal\Contracts\BusinessRegistryInterface;
use OrmUniversal\Contracts\Exceptions\BusinessOperationException;

/**
 * Explicit business registry: scopes, specifications and operations. Resolution
 * is always traceable; ambiguity throws instead of guessing.
 *
 * @api public
 */
final class BusinessRegistry implements BusinessRegistryInterface
{
    /** @var array<string, callable|string> */
    private array $scopes = [];

    /** @var array<string, string> */
    private array $specifications = [];

    /** @var array<string, callable|string> */
    private array $operations = [];

    /** @var array<string, string> */
    private array $scopeIndex = [];

    public function registerScope(string $name, callable|string $definition, ?string $model = null): void
    {
        $key = $model === null ? $name : $model.'@'.$name;
        $this->scopes[$key] = $definition;
        $this->scopeIndex[$key] = $name;
    }

    public function registerSpecification(string $name, string $specification): void
    {
        $this->specifications[$name] = $specification;
    }

    public function registerOperation(string $name, callable|string $operation, ?string $model = null): void
    {
        $key = $model === null ? $name : $model.'@'.$name;
        $this->operations[$key] = $operation;
    }

    public function hasScope(string $name, ?string $model = null): bool
    {
        if (isset($this->scopes[$name])) {
            return true;
        }

        return $model !== null && isset($this->scopes[$model.'@'.$name]);
    }

    public function hasSpecification(string $name): bool
    {
        return isset($this->specifications[$name]);
    }

    public function hasOperation(string $name, ?string $model = null): bool
    {
        if (isset($this->operations[$name])) {
            return true;
        }

        return $model !== null && isset($this->operations[$model.'@'.$name]);
    }

    public function scope(string $name, ?string $model = null): callable|string
    {
        if ($model !== null && isset($this->scopes[$model.'@'.$name])) {
            return $this->scopes[$model.'@'.$name];
        }

        if (isset($this->scopes[$name])) {
            return $this->scopes[$name];
        }

        // Ambiguity: several models define the scope and no model was requested.
        $candidates = array_filter(
            array_keys($this->scopes),
            static fn (string $key): bool => str_ends_with($key, '@'.$name),
        );

        if (count($candidates) > 1) {
            throw BusinessOperationException::ambiguousMethod($name, 'any', array_values($candidates));
        }

        if (count($candidates) === 1) {
            return $this->scopes[array_values($candidates)[0]];
        }

        throw BusinessOperationException::unknownMethod($name, $model ?? '*');
    }

    public function specification(string $name): string
    {
        return $this->specifications[$name]
            ?? throw BusinessOperationException::unknownMethod($name, 'specification');
    }

    public function operation(string $name, ?string $model = null): callable|string
    {
        if ($model !== null && isset($this->operations[$model.'@'.$name])) {
            return $this->operations[$model.'@'.$name];
        }

        return $this->operations[$name]
            ?? throw BusinessOperationException::unknownMethod($name, $model ?? '*');
    }

    /**
     * @return array<string, callable|string>
     */
    public function scopes(?string $model = null): array
    {
        if ($model === null) {
            return $this->scopes;
        }

        $result = [];
        foreach ($this->scopes as $key => $definition) {
            if (str_starts_with($key, $model.'@')) {
                $result[substr($key, strlen($model) + 1)] = $definition;
            }
        }

        return $result;
    }

    /**
     * @return array<string, callable|string>
     */
    public function operations(?string $model = null): array
    {
        if ($model === null) {
            return $this->operations;
        }

        $result = [];
        foreach ($this->operations as $key => $definition) {
            if (str_starts_with($key, $model.'@')) {
                $result[substr($key, strlen($model) + 1)] = $definition;
            }
        }

        return $result;
    }

    /**
     * Invoke a scope against a builder.
     */
    public function applyScope(string $name, object $query, ?string $model = null): object
    {
        $definition = $this->scope($name, $model);

        if ($definition instanceof Closure || is_callable($definition)) {
            $definition($query);
        } elseif (method_exists($query, 'applyScopeClass')) {
            $query->applyScopeClass($definition);
        } else {
            (new $definition)->apply($query);
        }

        return $query;
    }
}
