<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Immutable execution context. Contexts are passed explicitly or propagated by
 * scope; the core never relies on mutable global state (Octane/queue safe).
 *
 * @api public
 */
interface ContextInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function with(string $key, mixed $value): static;

    public function without(string $key): static;

    public function transaction(): ?TransactionInterface;

    public function tenant(): ?string;

    public function connectionName(): ?string;
}
