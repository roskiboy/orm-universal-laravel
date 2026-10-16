<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use OrmUniversal\Contracts\DialectInterface;
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\Exceptions\DialectException;
use OrmUniversal\Contracts\TypeInterface;
use OrmUniversal\Contracts\TypeRegistryInterface;

/**
 * Extensible type registry with the built-in universal types pre-registered.
 *
 * @api public
 */
final class TypeRegistry implements TypeRegistryInterface
{
    /** @var array<string, TypeInterface> */
    private array $types = [];

    public static function withDefaults(): self
    {
        $registry = new self;

        $registry->register(new UniversalType('string', TypeKind::String));
        $registry->register(new UniversalType('text', TypeKind::Text));
        $registry->register(new UniversalType('integer', TypeKind::Integer));
        $registry->register(new UniversalType('bigInteger', TypeKind::BigInteger));
        $registry->register(new UniversalType('unsignedInteger', TypeKind::UnsignedInteger));
        $registry->register(new UniversalType('decimal', TypeKind::Decimal));
        $registry->register(new UniversalType('float', TypeKind::Float));
        $registry->register(new UniversalType('boolean', TypeKind::Boolean));
        $registry->register(new UniversalType('date', TypeKind::Date));
        $registry->register(new UniversalType('time', TypeKind::Time));
        $registry->register(new UniversalType('datetime', TypeKind::DateTime));
        $registry->register(new UniversalType('timestamp', TypeKind::Timestamp));
        $registry->register(new UniversalType('binary', TypeKind::Binary));
        $registry->register(new UniversalType('blob', TypeKind::Blob));
        $registry->register(new UniversalType('json', TypeKind::Json));
        $registry->register(new UniversalType('jsonb', TypeKind::Jsonb));
        $registry->register(new UniversalType('uuid', TypeKind::Uuid));
        $registry->register(new UniversalType('ulid', TypeKind::Ulid));
        $registry->register(new UniversalType('enum', TypeKind::Enum));
        $registry->register(new UniversalType('array', TypeKind::Array));
        $registry->register(new UniversalType('object', TypeKind::Object));
        $registry->register(new UniversalType('vector', TypeKind::Vector));
        $registry->register(new UniversalType('geometry', TypeKind::Geometry));

        // Database specific types keep their identity: differences are never hidden.
        $registry->register(new UniversalType('postgresqlJsonb', TypeKind::Jsonb));
        $registry->register(new UniversalType('postgresqlArray', TypeKind::Array));
        $registry->register(new UniversalType('postgresqlEnum', TypeKind::Enum));
        $registry->register(new UniversalType('mysqlJson', TypeKind::Json));
        $registry->register(new UniversalType('mysqlGeometry', TypeKind::Geometry));
        $registry->register(new UniversalType('sqlServerUniqueIdentifier', TypeKind::Uuid));
        $registry->register(new UniversalType('oracleNumber', TypeKind::Decimal));

        return $registry;
    }

    public function register(TypeInterface $type, bool $override = false): void
    {
        $name = strtolower($type->name());

        if (! $override && isset($this->types[$name])) {
            throw new DialectException('Type ['.$name.'] is already registered.');
        }

        $this->types[$name] = $type;
    }

    public function has(string $name): bool
    {
        return isset($this->types[strtolower($name)]);
    }

    public function get(string $name): TypeInterface
    {
        return $this->types[strtolower($name)]
            ?? throw new DialectException('Unknown type ['.$name.'].');
    }

    public function resolve(string $name, ?DialectInterface $dialect = null): ?TypeInterface
    {
        return $this->types[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, TypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }
}
