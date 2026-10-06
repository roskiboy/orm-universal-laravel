<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Database\Connection as LaravelConnection;
use Illuminate\Support\Facades\DB;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Observability\InMemoryProfiler;

/**
 * Bridges Laravel database connections into the core executor without the core
 * depending on Laravel. Reuses the live PDO handle and detected server version.
 *
 * @api public
 */
final class LaravelConnectionAdapter
{
    /** @var array<string, QueryExecutor> */
    private array $executors = [];

    /** @var array<string, PdoConnection> */
    private array $connections = [];

    public function executor(LaravelConnection|string $connection = 'default', ?InMemoryProfiler $profiler = null): QueryExecutor
    {
        $resolved = $connection instanceof LaravelConnection ? $connection : $this->resolve($connection);
        $name = $resolved->getName();

        return $this->executors[$name] ??= new QueryExecutor(
            $this->pdoConnection($resolved, $profiler),
            $this->dialect($resolved),
            $profiler,
        );
    }

    public function dialectFor(LaravelConnection|string $connection = 'default'): AbstractDialect
    {
        return $this->dialect($connection instanceof LaravelConnection ? $connection : $this->resolve($connection));
    }

    public function pdoHandle(LaravelConnection $connection): PdoConnection
    {
        return $this->pdoConnection($connection, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(LaravelConnection $connection): array
    {
        return [
            'name' => $connection->getName(),
            'driver' => $connection->getDriverName(),
            'database' => $connection->getDatabaseName(),
            'server_version' => (string) $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
        ];
    }

    public function flush(?string $name = null): void
    {
        if ($name === null) {
            $this->executors = [];
            $this->connections = [];

            return;
        }

        unset($this->executors[$name], $this->connections[$name]);
    }

    private function resolve(string $name): LaravelConnection
    {
        $connection = DB::connection($name);
        $connection->getPdo();

        return $connection;
    }

    private function pdoConnection(LaravelConnection $connection, ?InMemoryProfiler $profiler): PdoConnection
    {
        $name = $connection->getName();

        return $this->connections[$name] ??= new PdoConnection(
            $connection->getPdo(),
            $name,
            $profiler,
        );
    }

    private function dialect(LaravelConnection $connection): AbstractDialect
    {
        $pdo = $connection->getPdo();
        $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if ($connection->getDriverName() === 'sqlite') {
            $version = (string) $pdo->query('select sqlite_version()')->fetchColumn();
        }

        return DialectResolver::resolve($connection->getDriverName(), $version);
    }
}
