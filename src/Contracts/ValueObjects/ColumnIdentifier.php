<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use InvalidArgumentException;

/**
 * Reference to a (optionally qualified) column.
 *
 * @api public
 */
final class ColumnIdentifier implements \Stringable
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $table = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Column name cannot be empty.');
        }
    }

    public static function fromString(string $identifier): self
    {
        $parts = explode('.', trim($identifier));

        return match (count($parts)) {
            1 => new self($parts[0]),
            2 => new self($parts[1], $parts[0]),
            default => throw new InvalidArgumentException("Invalid column identifier [{$identifier}]."),
        };
    }

    public function qualifiedName(): string
    {
        return $this->table !== null ? $this->table.'.'.$this->name : $this->name;
    }

    public function __toString(): string
    {
        return $this->qualifiedName();
    }
}
