<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use Illuminate\Database\Query\Builder;
use OrmUniversal\Laravel\Analysis\ParityAnalyzer;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Migration\SchemaBuilder;
use PHPUnit\Framework\TestCase;

final class ParityAnalyzerTest extends TestCase
{
    public function test_classifies_fixture_sources_into_parity_alias_and_gap(): void
    {
        $analyzer = new ParityAnalyzer;

        $result = $analyzer->compare(
            'Illuminate\Database\Query\Builder',
            [QueryBuilder::class, SelectQuery::class],
        );

        self::assertSame('Illuminate\Database\Query\Builder', $result['source']);
        self::assertContains('whereLike', $result['parity']);
        self::assertContains('whereToday', $result['parity']);
        self::assertContains('insertOrIgnore', $result['parity']);
        self::assertContains('from', $result['parity']);
        self::assertContains('whereFullText', $result['parity']);
        self::assertSame('lock', $result['alias']['lockForUpdate']);
        self::assertArrayHasKey('straightJoin', $result['defer']);
        self::assertArrayHasKey('useWritePdo', $result['na']);
        self::assertSame(0, $result['counts'][ParityAnalyzer::GAP]);
        self::assertSame(
            array_sum($result['counts']),
            count($result['parity']) + count($result['alias']) + count($result['na']) + count($result['defer']) + count($result['gap']),
        );
    }

    public function test_eloquent_defaults_to_hybrid_architecture(): void
    {
        $analyzer = new ParityAnalyzer;

        $result = $analyzer->compare('Illuminate\Database\Eloquent\Builder', [QueryBuilder::class]);

        self::assertSame(0, $result['counts'][ParityAnalyzer::GAP]);
        self::assertArrayHasKey('whereBelongsTo', $result['na']);
        self::assertContains('value', $result['parity']);
    }

    public function test_schema_blueprint_is_declarative_by_design(): void
    {
        $analyzer = new ParityAnalyzer;

        $result = $analyzer->compare('Illuminate\Database\Schema\Blueprint', [SchemaBuilder::class]);

        self::assertSame(0, $result['counts'][ParityAnalyzer::GAP]);
        self::assertArrayHasKey('string', $result['na']);
        self::assertNotEmpty($result['parity']);
    }

    public function test_analyzer_handles_unknown_source_class(): void
    {
        $analyzer = new ParityAnalyzer;

        $result = $analyzer->compare('App\\Not\\A\\Class', [QueryBuilder::class]);

        self::assertSame([], $result['parity']);
        self::assertSame(0, $result['counts'][ParityAnalyzer::PARITY]);
    }

    public function test_laravel_query_builder_class_is_available_in_test_environment(): void
    {
        self::assertTrue(class_exists(Builder::class));
    }
}
