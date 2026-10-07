<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;

/**
 * Registers the `ude-*` connection drivers. In this iteration they are
 * **passthrough**: the underlying connection is the standard Laravel connection
 * for the base driver, so enabling them never changes results. The AST fast-path
 * optimizer is layered on top in the next iteration, with a kill switch.
 *
 * @api public
 */
final class UdeConnectionFactory
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

    public static function register(DatabaseManager $database): void
    {
        foreach (self::DRIVERS as $udeDriver => $baseDriver) {
            try {
                $database->extend($udeDriver, static function (array $config, string $name) use ($baseDriver): Connection {
                    $factory = new ConnectionFactory(app());
                    $config['driver'] = $baseDriver;

                    return $factory->make($config, $name);
                });
            } catch (\InvalidArgumentException) {
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
}
