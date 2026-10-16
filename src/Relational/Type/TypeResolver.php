<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\Exceptions\DialectException;
use OrmUniversal\Contracts\TypeInterface;

/**
 * Resolves database specific type names into universal types without hiding
 * important differences (jsonb stays jsonb, uniqueidentifier maps to uuid).
 *
 * @api public
 */
final class TypeResolver
{
    /** @var array<string, string> database type => universal type name */
    private const ALIASES = [
        'varchar' => 'string',
        'varchar2' => 'string',
        'nvarchar' => 'string',
        'char' => 'string',
        'nchar' => 'string',
        'text' => 'text',
        'ntext' => 'text',
        'clob' => 'text',
        'longtext' => 'text',
        'mediumtext' => 'text',
        'int' => 'integer',
        'int2' => 'integer',
        'int4' => 'integer',
        'integer' => 'integer',
        'smallint' => 'integer',
        'tinyint' => 'integer',
        'mediumint' => 'integer',
        'int8' => 'bigInteger',
        'bigint' => 'bigInteger',
        'number' => 'decimal',
        'numeric' => 'decimal',
        'decimal' => 'decimal',
        'money' => 'decimal',
        'float' => 'float',
        'float4' => 'float',
        'float8' => 'float',
        'double' => 'float',
        'double precision' => 'float',
        'real' => 'float',
        'binary_double' => 'float',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'bit' => 'boolean',
        'date' => 'date',
        'time' => 'time',
        'datetime' => 'datetime',
        'datetime2' => 'datetime',
        'smalldatetime' => 'datetime',
        'timestamp' => 'timestamp',
        'timestamptz' => 'timestamp',
        'datetimeoffset' => 'timestamp',
        'binary' => 'binary',
        'varbinary' => 'binary',
        'raw' => 'binary',
        'blob' => 'blob',
        'bytea' => 'blob',
        'image' => 'blob',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'uuid' => 'uuid',
        'uniqueidentifier' => 'uuid',
        'ulid' => 'ulid',
        'enum' => 'enum',
        'array' => 'array',
        'object' => 'object',
        'vector' => 'vector',
        'geometry' => 'geometry',
        'geography' => 'geometry',
        'sdo_geometry' => 'geometry',
    ];

    public function __construct(
        private readonly TypeRegistry $registry,
    ) {}

    public function resolve(string $databaseType): ?TypeInterface
    {
        $normalized = strtolower(trim($databaseType));

        // Strip parameters: varchar(255) -> varchar, number(10,2) -> number
        $base = (string) preg_replace('/\s*\(.*$/', '', $normalized);

        $candidate = self::ALIASES[$base] ?? null;

        if ($candidate === null) {
            return null;
        }

        return $this->registry->resolve($candidate);
    }

    public function forKind(TypeKind $kind): ?TypeInterface
    {
        return $this->registry->resolve($kind->value);
    }

    public function require(string $databaseType): TypeInterface
    {
        return $this->resolve($databaseType)
            ?? throw new DialectException('No universal type registered for database type ['.$databaseType.'].');
    }

    /**
     * @return array<string, string>
     */
    public function aliases(): array
    {
        return self::ALIASES;
    }
}
