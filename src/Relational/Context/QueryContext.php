<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Query building context: connection name, dialect, tenant and free-form options.
 * Immutable; safe to share across Octane workers.
 *
 * @api public
 */
final class QueryContext
{
    /**
     * @param  array<string, mixed>  $options
     */
    private function __construct(
        private readonly ?string $connection = null,
        private readonly ?AbstractDialect $dialect = null,
        private readonly ?string $tenant = null,
        private readonly array $options = [],
    ) {}

    public static function make(
        ?string $connection = null,
        ?AbstractDialect $dialect = null,
        ?string $tenant = null,
        array $options = [],
    ): self {
        return new self($connection, $dialect, $tenant, $options);
    }

    public function connection(): ?string
    {
        return $this->connection;
    }

    public function dialect(): ?AbstractDialect
    {
        return $this->dialect;
    }

    public function tenant(): ?string
    {
        return $this->tenant;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function withConnection(?string $connection): self
    {
        return new self($connection, $this->dialect, $this->tenant, $this->options);
    }

    public function withDialect(AbstractDialect $dialect): self
    {
        return new self($this->connection, $dialect, $this->tenant, $this->options);
    }

    public function withTenant(?string $tenant): self
    {
        return new self($this->connection, $this->dialect, $tenant, $this->options);
    }

    public function withOption(string $key, mixed $value): self
    {
        return new self($this->connection, $this->dialect, $this->tenant, array_merge($this->options, [$key => $value]));
    }
}
