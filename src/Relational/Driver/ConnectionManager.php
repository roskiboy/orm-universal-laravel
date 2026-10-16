<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Driver;

use OrmUniversal\Contracts\ConnectionInterface;
use OrmUniversal\Contracts\Exceptions\ConnectionException;
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;

/**
 * Multi-connection manager. Connections are created lazily and can be closed and
 * purged between requests in long-running workers.
 *
 * @api public
 */
final class ConnectionManager
{
    /** @var array<string, ConnectionInterface> */
    private array $connections = [];

    /**
     * @param  array<string, ConnectionConfig>  $configs
     */
    public function __construct(
        private array $configs = [],
        private readonly PdoDriver $driver = new PdoDriver,
    ) {}

    public function add(string $name, ConnectionConfig $config): void
    {
        $this->configs[$name] = $config->name === null ? $config->with(['name' => $name]) : $config;
        unset($this->connections[$name]);
    }

    public function connection(?string $name = null): ConnectionInterface
    {
        $name ??= 'default';

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $config = $this->configs[$name] ?? throw new ConnectionException('No connection configured under ['.$name.'].');

        return $this->connections[$name] = $this->driver->connect($config);
    }

    public function has(string $name): bool
    {
        return isset($this->configs[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->configs);
    }

    public function close(string $name): void
    {
        if (isset($this->connections[$name])) {
            $this->connections[$name]->close();
            unset($this->connections[$name]);
        }
    }

    public function purge(): void
    {
        foreach (array_keys($this->connections) as $name) {
            $this->close($name);
        }
    }
}
