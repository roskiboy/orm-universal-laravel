<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use OrmUniversal\Contracts\Enums\RawType;

/**
 * Explicit raw SQL fragment. Values never are raw: they always carry bindings.
 *
 * @api public
 */
final class SqlFragment implements \Stringable
{
    /**
     * @param  list<mixed>  $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly RawType $type = RawType::Trusted,
        public readonly array $bindings = [],
    ) {
        if ($type === RawType::Value && $bindings === []) {
            throw new \InvalidArgumentException('A raw value fragment must carry at least one binding.');
        }
    }

    public static function trusted(string $sql): self
    {
        return new self($sql, RawType::Trusted);
    }

    /**
     * @param  list<mixed>  $bindings
     */
    public static function parameterized(string $sql, array $bindings): self
    {
        return new self($sql, RawType::Parameterized, $bindings);
    }

    public static function identifier(string $identifier): self
    {
        return new self($identifier, RawType::Identifier);
    }

    public static function value(mixed $value): self
    {
        return new self('?', RawType::Value, [$value]);
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
