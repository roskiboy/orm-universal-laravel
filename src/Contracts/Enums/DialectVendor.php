<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * Known dialect vendors. New vendors can be registered without changing the core;
 * this enum only enumerates the built-in ones.
 *
 * @api public
 */
enum DialectVendor: string
{
    case Mysql = 'mysql';
    case Mariadb = 'mariadb';
    case Postgresql = 'postgresql';
    case Sqlite = 'sqlite';
    case SqlServer = 'sqlserver';
    case Oracle = 'oracle';
    case Mongodb = 'mongodb';
    case Redis = 'redis';

    public function family(): DatabaseFamily
    {
        return match ($this) {
            self::Mongodb => DatabaseFamily::Document,
            self::Redis => DatabaseFamily::KeyValue,
            default => DatabaseFamily::Relational,
        };
    }

    /**
     * Whether the vendor belongs to the MySQL protocol/compatibility family.
     */
    public function isMysqlFamily(): bool
    {
        return $this === self::Mysql || $this === self::Mariadb;
    }

    /**
     * Parse a vendor from a driver name (PDO driver, Laravel driver, DSN scheme).
     */
    public static function fromDriverName(string $driver): ?self
    {
        return match (strtolower(trim($driver))) {
            'mysql' => self::Mysql,
            'mariadb' => self::Mariadb,
            'pgsql', 'postgres', 'postgresql' => self::Postgresql,
            'sqlite', 'sqlite3' => self::Sqlite,
            'sqlsrv', 'mssql', 'sqlserver' => self::SqlServer,
            'oci', 'oracle' => self::Oracle,
            'mongodb', 'mongo' => self::Mongodb,
            'redis' => self::Redis,
            default => null,
        };
    }

    /**
     * Default capability baseline version for the vendor, used when the server
     * version cannot be detected. Never used to claim versioned support.
     */
    public function defaultVersion(): DialectVersion
    {
        return match ($this) {
            self::Mysql => new DialectVersion($this, '8.4'),
            self::Mariadb => new DialectVersion($this, '11.6'),
            self::Postgresql => new DialectVersion($this, '16.0'),
            self::Sqlite => new DialectVersion($this, '3.35.0'),
            self::SqlServer => new DialectVersion($this, '2019'),
            self::Oracle => new DialectVersion($this, '19.0'),
            self::Mongodb => new DialectVersion($this, '8.0'),
            self::Redis => new DialectVersion($this, '7.0'),
        };
    }
}
