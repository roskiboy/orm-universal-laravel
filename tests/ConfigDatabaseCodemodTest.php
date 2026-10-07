<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use OrmUniversal\Laravel\Analysis\ConfigDatabaseCodemod;
use PHPUnit\Framework\TestCase;

final class ConfigDatabaseCodemodTest extends TestCase
{
    private const CONFIG = <<<'PHP'
<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => database_path('database.sqlite'),
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
        ],
        'mariadb' => [
            'driver' => 'mariadb',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
        ],
        'dynamic' => [
            'driver' => env('DB_DRIVER'),
        ],
        'already' => [
            'driver' => 'ude-sqlite',
        ],
    ],
];
PHP;

    public function test_analyze_finds_only_literal_eligible_drivers(): void
    {
        $edits = (new ConfigDatabaseCodemod)->analyze(self::CONFIG);

        self::assertSame(
            ['sqlite', 'mysql', 'mariadb', 'pgsql'],
            array_column($edits, 'connection'),
        );
        self::assertSame('ude-sqlite', $edits[0]['proposed']);
        self::assertSame('ude-pgsql', $edits[3]['proposed']);
    }

    public function test_apply_rewrites_only_the_selected_literals(): void
    {
        $codemod = new ConfigDatabaseCodemod;
        $edits = array_values(array_filter(
            $codemod->analyze(self::CONFIG),
            static fn (array $edit): bool => $edit['connection'] === 'mariadb',
        ));

        $proposed = $codemod->apply(self::CONFIG, $edits);

        self::assertStringContainsString("'driver' => 'ude-mariadb'", $proposed);
        self::assertStringContainsString("'driver' => 'mysql'", $proposed);
        self::assertStringContainsString("'driver' => 'sqlite'", $proposed);
        self::assertStringContainsString("'driver' => env('DB_DRIVER')", $proposed);
        self::assertSame(substr_count(self::CONFIG, 'ude-') + 1, substr_count($proposed, 'ude-'));
        self::assertSame(1, substr_count($proposed, 'ude-mariadb'));
        self::assertSame(substr_count(self::CONFIG, 'mariadb'), substr_count($proposed, 'mariadb'));
    }

    public function test_apply_is_idempotent(): void
    {
        $codemod = new ConfigDatabaseCodemod;
        $once = $codemod->apply(self::CONFIG, $codemod->analyze(self::CONFIG));

        self::assertSame([], $codemod->analyze($once));
        self::assertSame($once, $codemod->apply($once, $codemod->analyze($once)));
    }

    public function test_leading_and_trailing_bytes_are_preserved(): void
    {
        $codemod = new ConfigDatabaseCodemod;
        $edits = $codemod->analyze(self::CONFIG);
        $proposed = $codemod->apply(self::CONFIG, $edits);

        $first = $edits[0];

        self::assertStringStartsWith(substr(self::CONFIG, 0, $first['start']), $proposed);
        self::assertStringEndsWith(substr(self::CONFIG, -60), $proposed);
    }
}
