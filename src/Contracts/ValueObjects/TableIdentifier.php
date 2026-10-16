<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use InvalidArgumentException;

/**
 * Reference to a (optionally qualified, optionally aliased) table.
 *
 * @api public
 */
final class TableIdentifier implements \Stringable
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $schema = null,
        public readonly ?string $alias = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Table name cannot be empty.');
        }
    }

    public static function fromString(string $identifier, ?string $alias = null): self
    {
        $parts = explode('.', trim($identifier));

        return match (count($parts)) {
            1 => new self($parts[0], null, $alias),
            2 => new self($parts[1], $parts[0], $alias),
            default => throw new InvalidArgumentException("Invalid table identifier [{$identifier}]."),
        };
    }

    public function withAlias(string $alias): self
    {
        return new self($this->name, $this->schema, $alias);
    }

    public function qualifiedName(): string
    {
        return $this->schema !== null ? $this->schema.'.'.$this->name : $this->name;
    }

    public function __toString(): string
    {
        return $this->qualifiedName();
    }
}
