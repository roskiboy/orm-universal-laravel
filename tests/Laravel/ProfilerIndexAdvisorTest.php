<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use OrmUniversal\Laravel\Analysis\ProfilerIndexAdvisor;
use PHPUnit\Framework\TestCase;

final class ProfilerIndexAdvisorTest extends TestCase
{
    public function test_suggests_indexes_for_where_columns(): void
    {
        $advice = (new ProfilerIndexAdvisor)->suggest([
            'select * from "sellers" where "is_active" = ? and "name" like ? order by "id" asc',
            'select count(*) as "aggregate" from `treasury_movements` where `seller_id` = ? and `amount` > ?',
        ]);

        self::assertSame(['sellers', 'sellers', 'treasury_movements', 'treasury_movements'], array_column($advice, 'table'));
        self::assertSame(['is_active', 'name', 'seller_id', 'amount'], array_column($advice, 'column'));
        self::assertStringContainsString('CREATE INDEX sellers_is_active_idx', $advice[0]['suggestion']);
    }

    public function test_deduplicates_repeated_samples(): void
    {
        $advisor = new ProfilerIndexAdvisor;
        $advice = $advisor->suggest([
            'select * from "sellers" where "is_active" = ?',
            'select "id" from "sellers" where "is_active" = ?',
        ]);

        self::assertCount(1, $advice);
        self::assertSame('is_active', $advice[0]['column']);
    }

    public function test_ignores_samples_without_table_or_where(): void
    {
        $advice = (new ProfilerIndexAdvisor)->suggest([
            '',
            'select 1',
            'insert into sellers (name) values (?)',
        ]);

        self::assertSame([], $advice);
    }
}
