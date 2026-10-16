<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Driver;

use OrmUniversal\Contracts\ConnectionInterface;
use OrmUniversal\Contracts\DriverInterface;
use OrmUniversal\Contracts\Exceptions\DriverException;
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;
use PDO;
use PDOException;

/**
 * Creates PDO connections. Sensible defaults: exceptions on error, associative
 * fetch, no persistent connections (Octane reuses the connection pool safely).
 *
 * @api public
 */
final class PdoDriver implements DriverInterface
{
    public function name(): string
    {
        return 'pdo';
    }

    public function connect(ConnectionConfig $config): ConnectionInterface
    {
        $driver = strtolower($config->driver);

        if (! in_array($driver, PDO::getAvailableDrivers(), true)) {
            throw new DriverException(
                'PDO driver ['.$driver.'] is not available. Installed drivers: '.implode(', ', PDO::getAvailableDrivers()).'.',
            );
        }

        $options = $config->options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            $pdo = new PDO(
                $config->toDsn(),
                $config->username,
                $config->password,
                $options,
            );
        } catch (PDOException $exception) {
            throw new DriverException(
                'Unable to connect to ['.$driver.'] database ['.($config->database ?? '?').']: '.$exception->getMessage(),
                0,
                $exception,
            );
        }

        return new PdoConnection($pdo, $config->name ?? 'default');
    }

    public function supports(ConnectionConfig $config): bool
    {
        return in_array(strtolower($config->driver), PDO::getAvailableDrivers(), true);
    }

    public function requiredExtensions(): array
    {
        return ['pdo'];
    }
}
