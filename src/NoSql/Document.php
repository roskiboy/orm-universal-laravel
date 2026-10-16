<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql;

/**
 * Document value object: an associative payload plus its identifier.
 *
 * @api public
 */
final class Document implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $data = [],
        public readonly int|string|null $id = null,
    ) {}

    public static function fromArray(array $data, int|string|null $id = null): self
    {
        $id ??= $data['_id'] ?? $data['id'] ?? null;

        return new self($data, $id);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Documents are immutable; use a mutable copy.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Documents are immutable; use a mutable copy.');
    }
}
