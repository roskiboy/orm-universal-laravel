<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Tests\ValueObjects;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use PHPUnit\Framework\TestCase;

final class DialectVersionTest extends TestCase
{
    public function test_parses_components_from_server_strings(): void
    {
        $version = DialectVersion::parse(DialectVendor::Mariadb, '11.6.2-MariaDB-log');

        self::assertSame(11, $version->major());
        self::assertSame(6, $version->minor());
        self::assertSame(2, $version->patch());
        self::assertSame('11.6.2', $version->numeric());
    }

    public function test_at_least_and_less_than_compare_numerically(): void
    {
        $version = DialectVersion::parse(DialectVendor::Postgresql, '18.0');

        self::assertTrue($version->atLeast(15));
        self::assertTrue($version->atLeast(18, 0));
        self::assertFalse($version->atLeast(19));
        self::assertTrue($version->lessThan(19));
    }

    public function test_same_line_groups_major_versions(): void
    {
        $a = DialectVersion::parse(DialectVendor::Mysql, '8.0.36');
        $b = DialectVersion::parse(DialectVendor::Mysql, '8.4.3');
        $c = DialectVersion::parse(DialectVendor::Mysql, '9.7.2');

        self::assertTrue($a->sameLine($b));
        self::assertFalse($a->sameLine($c));
    }

    public function test_year_based_versions_are_supported(): void
    {
        $version = DialectVersion::parse(DialectVendor::SqlServer, '2019');

        self::assertSame(2019, $version->major());
        self::assertTrue($version->lessThan(2022));
    }

    public function test_vendor_family_classification(): void
    {
        self::assertTrue(DialectVendor::Mariadb->isMysqlFamily());
        self::assertFalse(DialectVendor::Postgresql->isMysqlFamily());
        self::assertSame(DialectVendor::Mongodb, DialectVendor::fromDriverName('mongodb'));
        self::assertNull(DialectVendor::fromDriverName('unknown'));
    }
}
