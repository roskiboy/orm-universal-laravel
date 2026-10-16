<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use InvalidArgumentException;
use OrmUniversal\Contracts\Enums\DialectVendor;

/**
 * Vendor + semantic version of a database server or dialect line.
 *
 * Examples: MariaDB 11.6.2, PostgreSQL 18.0, SQLite 3.49.2, SQL Server 2019,
 * Oracle 19.3, MongoDB 8.0.4, Redis 7.4.
 *
 * @api public
 */
final class DialectVersion implements \Stringable
{
    public function __construct(
        public readonly DialectVendor $vendor,
        public readonly string $raw,
    ) {
        if (trim($raw) === '') {
            throw new InvalidArgumentException('Dialect version cannot be empty.');
        }
    }

    public static function parse(DialectVendor|string $vendor, string $version): self
    {
        $enum = is_string($vendor) ? (DialectVendor::tryFrom(strtolower($vendor)) ?? throw new InvalidArgumentException("Unknown vendor [{$vendor}].")) : $vendor;

        return new self($enum, trim($version));
    }

    /**
     * Numeric components extracted from the raw version string.
     *
     * @return list<int>
     */
    public function components(): array
    {
        preg_match_all('/\d+/', $this->raw, $matches);

        return array_map('intval', $matches[0] ?? []);
    }

    public function major(): int
    {
        return $this->components()[0] ?? 0;
    }

    public function minor(): int
    {
        return $this->components()[1] ?? 0;
    }

    public function patch(): int
    {
        return $this->components()[2] ?? 0;
    }

    public function atLeast(int $major, int $minor = 0, int $patch = 0): bool
    {
        return version_compare($this->numeric(), sprintf('%d.%d.%d', $major, $minor, $patch), '>=');
    }

    public function lessThan(int $major, int $minor = 0, int $patch = 0): bool
    {
        return version_compare($this->numeric(), sprintf('%d.%d.%d', $major, $minor, $patch), '<');
    }

    public function numeric(): string
    {
        $components = $this->components();

        return sprintf('%d.%d.%d', $components[0] ?? 0, $components[1] ?? 0, $components[2] ?? 0);
    }

    public function sameLine(self $other): bool
    {
        return $this->vendor === $other->vendor && $this->major() === $other->major();
    }

    public function __toString(): string
    {
        return $this->vendor->value.' '.$this->raw;
    }
}
