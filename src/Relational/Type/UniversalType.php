<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use OrmUniversal\Contracts\DialectInterface;
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\TypeInterface;

/**
 * Universal type: one class covering every logical kind plus parameters. The
 * dialect specific spelling is resolved at DDL time.
 *
 * @api public
 */
final class UniversalType implements TypeInterface
{
    /**
     * @param  array<string, mixed>  $parameters  length, precision, scale, values, size
     */
    public function __construct(
        private readonly string $name,
        private readonly TypeKind $kind,
        private readonly array $parameters = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): TypeKind
    {
        return $this->kind;
    }

    public function databaseType(DialectInterface $dialect): string
    {
        $vendor = $dialect->vendor()->value;
        $length = $this->parameters['length'] ?? null;
        $precision = $this->parameters['precision'] ?? 10;
        $scale = $this->parameters['scale'] ?? 2;

        return match ($this->kind) {
            TypeKind::String => match ($vendor) {
                'oracle' => 'varchar2('.($length ?? 255).')',
                'sqlserver' => 'nvarchar('.($length ?? 255).')',
                default => 'varchar('.($length ?? 255).')',
            },
            TypeKind::Text => match ($vendor) {
                'oracle' => 'clob',
                'sqlserver' => 'nvarchar(max)',
                'mysql', 'mariadb' => 'longtext',
                default => 'text',
            },
            TypeKind::Integer => match ($vendor) {
                'oracle' => 'number(10)',
                'sqlserver' => 'int',
                default => 'integer',
            },
            TypeKind::BigInteger => match ($vendor) {
                'oracle' => 'number(19)',
                'sqlserver' => 'bigint',
                default => 'bigint',
            },
            TypeKind::UnsignedInteger => match ($vendor) {
                'mysql', 'mariadb' => 'int unsigned',
                'oracle' => 'number(10)',
                default => 'bigint',
            },
            TypeKind::Decimal => match ($vendor) {
                'oracle' => 'number('.$precision.','.$scale.')',
                'sqlserver' => 'decimal('.$precision.','.$scale.')',
                default => 'decimal('.$precision.','.$scale.')',
            },
            TypeKind::Float => match ($vendor) {
                'oracle' => 'binary_double',
                'mysql', 'mariadb' => 'double',
                'sqlserver' => 'float',
                default => 'double precision',
            },
            TypeKind::Boolean => match ($vendor) {
                'oracle' => 'number(1)',
                'sqlserver' => 'bit',
                'sqlite' => 'integer',
                default => 'boolean',
            },
            TypeKind::Date => 'date',
            TypeKind::Time => match ($vendor) {
                'oracle', 'sqlserver' => 'time',
                default => 'time',
            },
            TypeKind::DateTime => match ($vendor) {
                'sqlserver' => 'datetime2',
                'oracle' => 'date',
                default => 'datetime',
            },
            TypeKind::Timestamp => match ($vendor) {
                'oracle' => 'timestamp',
                'sqlserver' => 'datetime2',
                default => 'timestamp',
            },
            TypeKind::Binary => match ($vendor) {
                'sqlserver' => 'varbinary('.($length ?? 255).')',
                'oracle' => 'raw('.($length ?? 255).')',
                default => 'varbinary('.($length ?? 255).')',
            },
            TypeKind::Blob => match ($vendor) {
                'oracle' => 'blob',
                'sqlserver' => 'varbinary(max)',
                'postgresql' => 'bytea',
                default => 'blob',
            },
            TypeKind::Json => match ($vendor) {
                'postgresql' => $dialect->supports('supportsJsonb') ? 'jsonb' : 'json',
                'mariadb' => $dialect->supports('supportsJson') ? 'longtext' : 'text',
                'sqlite' => 'text',
                default => 'json',
            },
            TypeKind::Jsonb => $dialect->supports('supportsJsonb') ? 'jsonb' : 'json',
            TypeKind::Uuid => match ($vendor) {
                'postgresql' => 'uuid',
                'sqlserver' => 'uniqueidentifier',
                'oracle' => 'raw(16)',
                default => 'char(36)',
            },
            TypeKind::Ulid => 'char(26)',
            TypeKind::Enum => match ($vendor) {
                'mysql', 'mariadb' => 'enum('.implode(', ', array_map(static fn (string $value): string => "'".str_replace("'", "''", $value)."'", $this->parameters['values'] ?? [])).')',
                default => 'varchar(255)',
            },
            TypeKind::Array => $dialect->supports('supportsArrays') ? 'text[]' : 'json',
            TypeKind::Object => $dialect->supports('supportsJsonb') ? 'jsonb' : 'json',
            TypeKind::Vector => match ($vendor) {
                'postgresql' => 'vector('.($length ?? 1536).')',
                'oracle' => 'vector('.($length ?? 1536).', float32)',
                default => 'vector('.($length ?? 1536).')',
            },
            TypeKind::Geometry => match ($vendor) {
                'oracle' => 'sdo_geometry',
                'sqlite' => 'text',
                default => 'geometry',
            },
            TypeKind::Custom => (string) ($this->parameters['database_type'] ?? ''),
        };
    }

    public function phpType(): string
    {
        return match ($this->kind) {
            TypeKind::Integer, TypeKind::BigInteger, TypeKind::UnsignedInteger => 'int',
            TypeKind::Decimal, TypeKind::Float => 'float|string',
            TypeKind::Boolean => 'bool',
            TypeKind::Json, TypeKind::Jsonb, TypeKind::Array, TypeKind::Object => 'array',
            TypeKind::Blob, TypeKind::Binary => 'string',
            default => 'string',
        };
    }

    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->kind) {
            TypeKind::Integer, TypeKind::BigInteger, TypeKind::UnsignedInteger => (int) $value,
            TypeKind::Float => (float) $value,
            TypeKind::Boolean => is_string($value) ? in_array(strtolower($value), ['1', 'true', 't', 'yes'], true) : (bool) $value,
            TypeKind::Json, TypeKind::Jsonb, TypeKind::Array, TypeKind::Object => is_string($value) ? json_decode($value, true) : $value,
            default => (string) $value,
        };
    }

    public function serialize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->kind) {
            TypeKind::Json, TypeKind::Jsonb, TypeKind::Array, TypeKind::Object => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            TypeKind::Boolean => $value ? 1 : 0,
            default => $value,
        };
    }

    public function deserialize(mixed $value): mixed
    {
        return $this->cast($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
