<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/**
 * Registers the `ude-*` connection drivers. Each driver resolves to a concrete
 * Laravel connection subclass (same grammar, connectors and options as the
 * native driver) whose query builder adds the UDE AST fast path. `ude-oracle`
 * stays passthrough because Laravel does not ship an Oracle connection; install
 * an OCI8 resolver in the host application when needed.
 *
 * @api public
 */
final class UdeConnectionFactory extends ConnectionFactory
{
    /** @var array<string, string> ude driver => base Laravel driver */
    public const DRIVERS = [
        'ude-mysql' => 'mysql',
        'ude-mariadb' => 'mariadb',
        'ude-pgsql' => 'pgsql',
        'ude-sqlite' => 'sqlite',
        'ude-sqlsrv' => 'sqlsrv',
        'ude-oracle' => 'oracle',
    ];

    /** @var array<string, class-string<Connection>> */
    private const CONNECTIONS = [
        'ude-mysql' => UdeMySqlConnection::class,
        'ude-mariadb' => UdeMariaDbConnection::class,
        'ude-pgsql' => UdePostgresConnection::class,
        'ude-sqlite' => UdeSqliteConnection::class,
        'ude-sqlsrv' => UdeSqlServerConnection::class,
    ];

    public static function register(DatabaseManager $database): void
    {
        foreach (array_keys(self::DRIVERS) as $udeDriver) {
            try {
                $database->extend($udeDriver, static function (array $config, string $name): Connection {
                    $factory = new self(Container::getInstance());

                    return $factory->make($config, $name);
                });
            } catch (InvalidArgumentException) {
                // Driver already registered (provider booted twice): keep the first one.
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function drivers(): array
    {
        return array_keys(self::DRIVERS);
    }

    public function createConnector(array $config)
    {
        if (isset($config['driver'], self::DRIVERS[$config['driver']])) {
            $config['driver'] = self::DRIVERS[$config['driver']];
        }

        return parent::createConnector($config);
    }

    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        $class = self::CONNECTIONS[$driver] ?? null;

        if ($class !== null) {
            return new $class($connection, $database, $prefix, $config);
        }

        if (isset(self::DRIVERS[$driver])) {
            return parent::createConnection(self::DRIVERS[$driver], $connection, $database, $prefix, $config);
        }

        return parent::createConnection($driver, $connection, $database, $prefix, $config);
    }
}
