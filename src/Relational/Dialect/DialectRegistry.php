<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Exceptions\DialectException;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * Registry of dialect factories. Third parties can register new vendors or
 * override a version line without touching the core.
 *
 * @api public
 */
final class DialectRegistry
{
    /** @var array<string, callable(DialectVersion): AbstractDialect> */
    private array $factories = [];

    /** @var array<string, AbstractDialect> */
    private array $instances = [];

    public static function withDefaults(): self
    {
        $registry = new self;
        $registry->register(DialectVendor::Mysql, static fn (DialectVersion $version): AbstractDialect => new MySqlDialect($version, Capabilities\MySqlCapabilities::for($version)));
        $registry->register(DialectVendor::Mariadb, static fn (DialectVersion $version): AbstractDialect => new MariaDbDialect($version, Capabilities\MariaDbCapabilities::for($version)));
        $registry->register(DialectVendor::Postgresql, static fn (DialectVersion $version): AbstractDialect => new PostgreSqlDialect($version, Capabilities\PostgreSqlCapabilities::for($version)));
        $registry->register(DialectVendor::Sqlite, static fn (DialectVersion $version): AbstractDialect => new SqliteDialect($version, Capabilities\SqliteCapabilities::for($version)));
        $registry->register(DialectVendor::SqlServer, static fn (DialectVersion $version): AbstractDialect => new SqlServerDialect($version, Capabilities\SqlServerCapabilities::for($version)));
        $registry->register(DialectVendor::Oracle, static fn (DialectVersion $version): AbstractDialect => new OracleDialect($version, Capabilities\OracleCapabilities::for($version)));

        return $registry;
    }

    /**
     * @param  callable(DialectVersion): AbstractDialect  $factory
     */
    public function register(DialectVendor $vendor, callable $factory, bool $override = false): void
    {
        if (! $override && isset($this->factories[$vendor->value])) {
            throw new DialectException('Dialect factory already registered for ['.$vendor->value.'].');
        }

        $this->factories[$vendor->value] = $factory;
        unset($this->instances[$vendor->value]);
    }

    public function has(DialectVendor $vendor): bool
    {
        return isset($this->factories[$vendor->value]);
    }

    public function for(DialectVersion $version): AbstractDialect
    {
        $key = $version->vendor->value.':'.$version->numeric();

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $factory = $this->factories[$version->vendor->value]
            ?? throw new DialectException('No dialect registered for vendor ['.$version->vendor->value.'].');

        return $this->instances[$key] = $factory($version);
    }

    /**
     * @return list<string>
     */
    public function vendors(): array
    {
        return array_keys($this->factories);
    }
}
