<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql;

/**
 * NoSQL filter builder. Produces backend-neutral operator documents:
 * ['age' => ['$gte' => 18], '$or' => [...]]. Native operators are always allowed.
 *
 * @api public
 */
final class Filter implements \JsonSerializable
{
    /** @var array<string, mixed> */
    private array $filter = [];

    /**
     * @param  array<string, mixed>  $filter
     */
    public function __construct(array $filter = [])
    {
        $this->filter = $filter;
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    public static function make(array $filter = []): self
    {
        return new self($filter);
    }

    public function eq(string $field, mixed $value): self
    {
        return $this->and([$field => $value]);
    }

    public function neq(string $field, mixed $value): self
    {
        return $this->and([$field => ['$ne' => $value]]);
    }

    public function gt(string $field, mixed $value): self
    {
        return $this->and([$field => ['$gt' => $value]]);
    }

    public function gte(string $field, mixed $value): self
    {
        return $this->and([$field => ['$gte' => $value]]);
    }

    public function lt(string $field, mixed $value): self
    {
        return $this->and([$field => ['$lt' => $value]]);
    }

    public function lte(string $field, mixed $value): self
    {
        return $this->and([$field => ['$lte' => $value]]);
    }

    /**
     * @param  list<mixed>  $values
     */
    public function in(string $field, array $values): self
    {
        return $this->and([$field => ['$in' => array_values($values)]]);
    }

    public function exists(string $field, bool $exists = true): self
    {
        return $this->and([$field => ['$exists' => $exists]]);
    }

    public function regex(string $field, string $pattern): self
    {
        return $this->and([$field => ['$regex' => $pattern]]);
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    public function and(array $conditions): self
    {
        $this->filter = array_merge($this->filter, $conditions);

        return $this;
    }

    public function or(self ...$filters): self
    {
        $this->filter['$or'] = array_map(static fn (self $filter): array => $filter->toArray(), $filters);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->filter;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->filter;
    }
}
