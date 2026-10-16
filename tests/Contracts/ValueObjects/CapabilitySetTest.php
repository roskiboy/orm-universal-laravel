<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Tests\ValueObjects;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use PHPUnit\Framework\TestCase;

final class CapabilitySetTest extends TestCase
{
    public function test_supports_only_declared_capabilities(): void
    {
        $set = CapabilitySet::of([Capability::RETURNING, Capability::CTE]);

        self::assertTrue($set->supports(Capability::RETURNING));
        self::assertFalse($set->supports(Capability::JSONB));
        self::assertFalse($set->supports('unknownCapability'));
    }

    public function test_with_and_without_are_immutable(): void
    {
        $set = CapabilitySet::empty();

        $with = $set->with(Capability::JSON);

        self::assertFalse($set->supports(Capability::JSON));
        self::assertTrue($with->supports(Capability::JSON));
        self::assertFalse($with->without(Capability::JSON)->supports(Capability::JSON));
    }

    public function test_merge_prefers_other_set(): void
    {
        $a = CapabilitySet::of([Capability::CTE]);
        $b = CapabilitySet::empty()->with(Capability::JSON, true);

        $merged = $a->merge($b);

        self::assertTrue($merged->supports(Capability::CTE));
        self::assertTrue($merged->supports(Capability::JSON));
    }

    public function test_missing_and_diff(): void
    {
        $set = CapabilitySet::of([Capability::CTE, Capability::JSON]);

        self::assertSame([Capability::JSONB], $set->missing([Capability::CTE, Capability::JSONB]));
        self::assertSame(
            [Capability::CTE],
            array_keys($set->diff(CapabilitySet::of([Capability::JSON]))->all()),
        );
    }

    public function test_require_all_throws_with_full_report(): void
    {
        $set = CapabilitySet::of([Capability::CTE]);

        try {
            $set->requireAll([Capability::RETURNING], 'MariaDB', '10.4.0');
            self::fail('Expected UnsupportedFeatureException.');
        } catch (UnsupportedFeatureException $exception) {
            self::assertSame(Capability::RETURNING, $exception->feature);
            self::assertSame('MariaDB', $exception->engine);
            self::assertSame('10.4.0', $exception->engineVersion);
            self::assertStringContainsString('Feature: '.Capability::RETURNING, $exception->report());
            self::assertStringContainsString('Database: MariaDB', $exception->report());
        }
    }

    public function test_capability_set_accepts_arbitrary_capabilities(): void
    {
        $set = CapabilitySet::of(['vendorSpecificCapability']);

        self::assertTrue($set->supports('vendorSpecificCapability'));
    }
}
