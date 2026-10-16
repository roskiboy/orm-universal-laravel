<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * Resolves the concrete dialect from a driver name plus the raw server version
 * reported by the connection (PDO::ATTR_SERVER_VERSION or equivalent).
 *
 * SQL Server stores its release year in the major version (15 => 2019,
 * 16 => 2022, 17 => 2025); Oracle keeps semantic majors (19, 23...).
 *
 * @api public
 */
final class DialectResolver
{
    public static function vendorFromDriver(string $driver): ?DialectVendor
    {
        return DialectVendor::fromDriverName($driver);
    }

    public static function versionFromRaw(DialectVendor $vendor, string $rawVersion): DialectVersion
    {
        $normalized = trim($rawVersion);

        // MariaDB reports a MySQL compatible version string that embeds the name.
        if ($vendor === DialectVendor::Mysql && stripos($normalized, 'mariadb') !== false) {
            $vendor = DialectVendor::Mariadb;
        }

        if ($vendor === DialectVendor::SqlServer) {
            $major = (int) (preg_match('/^(\d+)/', $normalized, $match) === 1 ? $match[1] : 0);
            $year = match (true) {
                $major >= 17 => '2025',
                $major >= 16 => '2022',
                $major >= 15 => '2019',
                default => '2016',
            };

            return new DialectVersion($vendor, $year);
        }

        return new DialectVersion($vendor, $normalized);
    }

    public static function resolve(
        string $driver,
        ?string $rawVersion = null,
        ?DialectRegistry $registry = null,
    ): AbstractDialect {
        $registry ??= DialectRegistry::withDefaults();
        $vendor = self::vendorFromDriver($driver) ?? throw new \InvalidArgumentException("Unsupported driver [{$driver}].");

        $version = $rawVersion !== null && trim($rawVersion) !== ''
            ? self::versionFromRaw($vendor, $rawVersion)
            : $vendor->defaultVersion();

        // Re-check MariaDB after normalizing the raw version.
        if ($version->vendor !== $vendor && $registry->has($version->vendor)) {
            return $registry->for($version);
        }

        return $registry->for($version);
    }
}
