<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Immutable connection configuration. Usable outside Laravel.
 *
 * @api public
 */
final class ConnectionConfig
{
    /**
     * @param  array<int, mixed>  $options  PDO attributes
     * @param  array<string, mixed>  $settings  Free-form driver settings
     */
    public function __construct(
        public readonly string $driver,
        public readonly ?string $dsn = null,
        public readonly ?string $host = null,
        public readonly int|string|null $port = null,
        public readonly ?string $database = null,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $charset = null,
        public readonly ?string $schema = null,
        public readonly array $options = [],
        public readonly array $settings = [],
        public readonly ?string $name = null,
    ) {}

    /**
     * Build the PDO DSN when one is not provided explicitly.
     */
    public function toDsn(): string
    {
        if ($this->dsn !== null) {
            return $this->dsn;
        }

        return match (strtolower($this->driver)) {
            'mysql', 'mariadb' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s%s',
                $this->host ?? '127.0.0.1',
                $this->port ?? 3306,
                $this->database ?? '',
                $this->charset !== null ? ';charset='.$this->charset : '',
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $this->host ?? '127.0.0.1',
                $this->port ?? 5432,
                $this->database ?? '',
            ),
            'sqlite' => 'sqlite:'.($this->database ?? ':memory:'),
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s,%s;Database=%s',
                $this->host ?? '127.0.0.1',
                $this->port ?? 1433,
                $this->database ?? '',
            ),
            'oci' => sprintf('oci:dbname=%s/%s', $this->host ?? '127.0.0.1', $this->database ?? ''),
            default => throw new \InvalidArgumentException("Cannot build a DSN for driver [{$this->driver}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            driver: (string) ($overrides['driver'] ?? $this->driver),
            dsn: $overrides['dsn'] ?? $this->dsn,
            host: $overrides['host'] ?? $this->host,
            port: $overrides['port'] ?? $this->port,
            database: $overrides['database'] ?? $this->database,
            username: $overrides['username'] ?? $this->username,
            password: $overrides['password'] ?? $this->password,
            charset: $overrides['charset'] ?? $this->charset,
            schema: $overrides['schema'] ?? $this->schema,
            options: $overrides['options'] ?? $this->options,
            settings: $overrides['settings'] ?? $this->settings,
            name: $overrides['name'] ?? $this->name,
        );
    }
}
