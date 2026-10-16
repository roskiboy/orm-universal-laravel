<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Registry of universal and database specific types.
 *
 * @api public
 */
interface TypeRegistryInterface
{
    public function register(TypeInterface $type, bool $override = false): void;

    public function has(string $name): bool;

    public function get(string $name): TypeInterface;

    /**
     * Resolve a type by name or logical kind for a dialect.
     */
    public function resolve(string $name, ?DialectInterface $dialect = null): ?TypeInterface;

    /**
     * @return array<string, TypeInterface>
     */
    public function all(): array;
}
