<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

use OrmUniversal\Contracts\ContextInterface;
use OrmUniversal\Contracts\TransactionInterface;

/**
 * Immutable execution context. No global mutable state: transport tenant, locale,
 * request metadata, connection and transaction explicitly.
 *
 * @api public
 */
final class Context implements ContextInterface
{
    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(
        private readonly array $values = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function of(array $values): self
    {
        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function with(string $key, mixed $value): static
    {
        return new self(array_merge($this->values, [$key => $value]));
    }

    public function without(string $key): static
    {
        $values = $this->values;
        unset($values[$key]);

        return new self($values);
    }

    public function transaction(): ?TransactionInterface
    {
        $transaction = $this->values['transaction'] ?? null;

        return $transaction instanceof TransactionInterface ? $transaction : null;
    }

    public function tenant(): ?string
    {
        $tenant = $this->values['tenant'] ?? null;

        return $tenant === null ? null : (string) $tenant;
    }

    public function connectionName(): ?string
    {
        $connection = $this->values['connection'] ?? null;

        return $connection === null ? null : (string) $connection;
    }
}
