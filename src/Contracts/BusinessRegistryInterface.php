<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Registry for business scopes, operations and specifications. Extensible without
 * touching the core; resolution is explicit and traceable.
 *
 * @api public
 */
interface BusinessRegistryInterface
{
    /**
     * @param  callable|class-string  $definition
     */
    public function registerScope(string $name, callable|string $definition, ?string $model = null): void;

    /**
     * @param  class-string  $specification
     */
    public function registerSpecification(string $name, string $specification): void;

    /**
     * @param  callable|class-string  $operation
     */
    public function registerOperation(string $name, callable|string $operation, ?string $model = null): void;

    public function hasScope(string $name, ?string $model = null): bool;

    public function hasSpecification(string $name): bool;

    public function hasOperation(string $name, ?string $model = null): bool;

    public function scope(string $name, ?string $model = null): callable|string;

    public function specification(string $name): string;

    public function operation(string $name, ?string $model = null): callable|string;

    /**
     * @return array<string, callable|string>
     */
    public function scopes(?string $model = null): array;
}
