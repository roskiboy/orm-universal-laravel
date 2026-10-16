<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Database\Connection as LaravelConnection;
use Illuminate\Support\Facades\DB;
use OrmUniversal\Relational\Cache\QueryCache;
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

    private ?QueryCache $queryCache = null;

    private bool $reuseQueries = false;

    public function withQueryCache(QueryCache $queryCache): self
    {
        $this->queryCache = $queryCache;

        return $this;
    }

    public function hasQueryCache(): bool
    {
        return $this->queryCache !== null;
    }

    public function withQueryReuse(bool $enabled = true): self
    {
        $this->reuseQueries = $enabled;

        return $this;
    }

    public function executor(LaravelConnection|string $connection = 'default', ?InMemoryProfiler $profiler = null): QueryExecutor
    {
        $resolved = $connection instanceof LaravelConnection ? $connection : $this->resolve($connection);
        $name = $resolved->getName();

        if (isset($this->executors[$name])) {
            return $this->executors[$name];
        }

        $executor = new QueryExecutor(
            $this->pdoConnection($resolved, $profiler),
            $this->dialect($resolved),
            $profiler,
        );

        if ($this->queryCache !== null) {
            $executor = $executor->withQueryCache($this->queryCache);
        }

        if ($this->reuseQueries) {
            $executor = $executor->withQueryReuse(true);
        }

        return $this->executors[$name] = $executor;
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
            foreach ($this->executors as $executor) {
                $executor->flushDeferred();
            }

            $this->executors = [];
            $this->connections = [];

            return;
        }

        if (isset($this->executors[$name])) {
            $this->executors[$name]->flushDeferred();
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

        $driver = (string) $connection->getDriverName();
        if (str_starts_with($driver, 'ude-')) {
            $driver = substr($driver, 4);
        }

        if ($driver === 'sqlite') {
            $version = (string) $pdo->query('select sqlite_version()')->fetchColumn();
        }

        return DialectResolver::resolve($driver, $version);
    }
}
