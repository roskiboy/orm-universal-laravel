<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Tests\ValueObjects;

use InvalidArgumentException;
use OrmUniversal\Contracts\Enums\RawType;
use OrmUniversal\Contracts\ValueObjects\ColumnIdentifier;
use OrmUniversal\Contracts\ValueObjects\SqlFragment;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use PHPUnit\Framework\TestCase;

final class IdentifiersAndFragmentsTest extends TestCase
{
    public function test_table_identifier_parses_schema_and_alias(): void
    {
        $table = TableIdentifier::fromString('public.users', 'u');

        self::assertSame('users', $table->name);
        self::assertSame('public', $table->schema);
        self::assertSame('u', $table->alias);
        self::assertSame('public.users', $table->qualifiedName());
    }

    public function test_column_identifier_parses_table(): void
    {
        $column = ColumnIdentifier::fromString('users.email');

        self::assertSame('email', $column->name);
        self::assertSame('users', $column->table);
        self::assertSame('users.email', (string) $column);
    }

    public function test_empty_identifiers_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableIdentifier('');
    }

    public function test_raw_fragment_requires_explicit_type(): void
    {
        $trusted = SqlFragment::trusted('now()');
        $parameterized = SqlFragment::parameterized('coalesce(col, ?)', [0]);
        $value = SqlFragment::value(10);

        self::assertSame(RawType::Trusted, $trusted->type);
        self::assertSame(RawType::Parameterized, $parameterized->type);
        self::assertSame([0], $parameterized->bindings);
        self::assertSame(RawType::Value, $value->type);
        self::assertSame([10], $value->bindings);
    }

    public function test_raw_value_without_bindings_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SqlFragment('?', RawType::Value, []);
    }
}
