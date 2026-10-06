<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use OrmUniversal\Contracts\Enums\CompatibilityMode;
use OrmUniversal\Laravel\Adapters\LaravelFeatureRegistry;
use OrmUniversal\Laravel\Compatibility\LaravelCompatibilityLayer;
use PHPUnit\Framework\TestCase;

final class CompatibilityTest extends TestCase
{
    public function test_default_mode_is_hybrid_and_keeps_legacy_apis(): void
    {
        $layer = new LaravelCompatibilityLayer();

        self::assertSame(CompatibilityMode::Hybrid, $layer->mode());
        self::assertTrue($layer->allowsLegacyApis());
        self::assertTrue($layer->report()['legacy_apis_allowed']);
    }

    public function test_strict_mode_rejects_unclassified_raw_sql(): void
    {
        $layer = new LaravelCompatibilityLayer(CompatibilityMode::Strict);

        $this->expectException(\OrmUniversal\Contracts\Exceptions\QueryException::class);
        $layer->guardRawSql('delete from users');
    }

    public function test_strict_mode_accepts_explicit_raw_sql(): void
    {
        $layer = new LaravelCompatibilityLayer(CompatibilityMode::Strict);

        $layer->guardRawSql('delete from users', explicit: true);

        self::assertFalse($layer->allowsLegacyApis());
    }

    public function test_feature_detection_reports_laravel_capabilities(): void
    {
        $features = new LaravelFeatureRegistry();

        self::assertTrue($features->supports('eloquent_builder_macros'));
        self::assertTrue($features->supports('connection_pdo_accessor'));
        self::assertFalse($features->supports('unknown_feature'));
    }
}
