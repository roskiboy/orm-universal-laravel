<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Type;

use DateTimeImmutable;
use OrmUniversal\Relational\Type\CastApplier;
use PHPUnit\Framework\TestCase;
use stdClass;

enum CastApplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

final class CastApplierTest extends TestCase
{
    public function test_decimals_stay_strings_never_floats(): void
    {
        $applier = CastApplier::for(['price' => 'decimal:2']);

        self::assertSame('10.50', $applier->apply('price', '10.50'));
        self::assertSame('10.50', $applier->apply('price', 10.5));
        self::assertSame('10', $applier->apply('price', 10));
        self::assertIsString($applier->apply('price', 10.5));
    }

    public function test_json_like_casts_decode_to_array_or_object(): void
    {
        $applier = CastApplier::for(['meta' => 'array', 'payload' => 'object', 'tags' => 'json']);

        self::assertSame(['a' => 1], $applier->apply('meta', '{"a":1}'));
        self::assertSame(['a' => 1], $applier->apply('meta', ['a' => 1]));
        self::assertInstanceOf(stdClass::class, $applier->apply('payload', '{"a":1}'));
        self::assertSame([1, 2], $applier->apply('tags', '[1,2]'));
        self::assertSame([], $applier->apply('meta', 'not-json'));
    }

    public function test_scalar_and_boolean_casts(): void
    {
        $applier = CastApplier::for([
            'active' => 'bool',
            'qty' => 'int',
            'ratio' => 'float',
            'name' => 'string',
        ]);

        self::assertTrue($applier->apply('active', 1));
        self::assertFalse($applier->apply('active', '0'));
        self::assertSame(7, $applier->apply('qty', '7'));
        self::assertSame(2.5, $applier->apply('ratio', '2.5'));
        self::assertSame('ana', $applier->apply('name', 'ana'));
    }

    public function test_uuid_dates_enums_and_vectors(): void
    {
        $applier = CastApplier::for([
            'id' => 'uuid',
            'created_at' => 'datetime',
            'status' => CastApplierStatus::class,
            'embedding' => 'vector',
        ]);

        self::assertSame('a1b2c3', $applier->apply('id', 'A1B2C3'));
        self::assertInstanceOf(DateTimeImmutable::class, $applier->apply('created_at', '2026-10-07 12:00:00'));
        self::assertSame(CastApplierStatus::Active, $applier->apply('status', 'active'));
        self::assertSame([1.0, 2.5], $applier->apply('embedding', '[1, 2.5]'));
        self::assertSame([1.0, 2.0], $applier->apply('embedding', '1,2'));
    }

    public function test_unknown_casts_and_nulls_pass_through(): void
    {
        $applier = CastApplier::for(['secret' => 'hashed', 'name' => 'string']);

        self::assertSame('abc', $applier->apply('secret', 'abc'));
        self::assertNull($applier->apply('name', null));
        self::assertSame('x', $applier->apply('unknown_column', 'x'));
    }

    public function test_apply_row_only_touches_cast_columns(): void
    {
        $applier = CastApplier::for(['price' => 'decimal:2', 'meta' => 'array']);

        $row = $applier->applyRow(['id' => 1, 'price' => '10.50', 'meta' => '{"a":1}', 'other' => 'keep']);

        self::assertSame(1, $row['id']);
        self::assertSame('10.50', $row['price']);
        self::assertSame(['a' => 1], $row['meta']);
        self::assertSame('keep', $row['other']);
    }
}
