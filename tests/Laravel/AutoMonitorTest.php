<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use OrmUniversal\Laravel\Auto\VanillaMonitor;
use PHPUnit\Framework\TestCase;

final class AutoMonitorTest extends TestCase
{
    public function test_monitor_detects_duplicates_slow_queries_and_suggests(): void
    {
        $monitor = new VanillaMonitor(slowMs: 100, n1Threshold: 3);

        for ($i = 0; $i < 3; $i++) {
            $monitor->record((object) [
                'sql' => "select * from sellers where id = {$i}",
                'time' => 5.0,
                'connectionName' => 'mariadb',
            ]);
        }

        $monitor->record((object) [
            'sql' => 'select * from orders',
            'time' => 350.0,
            'connectionName' => 'mariadb',
        ]);

        self::assertSame(4, $monitor->count());
        self::assertCount(1, $monitor->duplicates(), 'Three equivalent queries must be flagged as a duplicated pattern.');
        self::assertCount(1, $monitor->slowQueries());
        self::assertNotEmpty($monitor->suggestions());

        $report = $monitor->report();
        self::assertSame('monitor', $report['mode']);
        self::assertSame(4, $report['queries']);
        self::assertSame(3, $report['thresholds']['n_plus_one']);

        $monitor->reset();
        self::assertSame(0, $monitor->count());
        self::assertSame([], $monitor->duplicates());
    }

    public function test_monitor_ignores_events_without_sql(): void
    {
        $monitor = new VanillaMonitor;

        $monitor->record((object) ['sql' => '', 'time' => 1.0, 'connectionName' => 'default']);
        $monitor->record((object) ['time' => 1.0]);

        self::assertSame(0, $monitor->count());
    }
}
