<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

/**
 * Placeholder target used when replaying specifications inside orWhere() closures.
 *
 * @api public
 */
final class NestedSpecificationTarget
{
    public function __construct(private readonly object $query) {}

    public function where(mixed ...$arguments): self
    {
        $this->query->where(...$arguments);

        return $this;
    }

    public function orWhere(mixed ...$arguments): self
    {
        $this->query->orWhere(...$arguments);

        return $this;
    }

    public function whereIn(...$arguments): self
    {
        $this->query->whereIn(...$arguments);

        return $this;
    }

    public function whereNull(...$arguments): self
    {
        $this->query->whereNull(...$arguments);

        return $this;
    }

    public function whereNotNull(...$arguments): self
    {
        $this->query->whereNotNull(...$arguments);

        return $this;
    }

    public function whereBetween(...$arguments): self
    {
        $this->query->whereBetween(...$arguments);

        return $this;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->query->{$method}(...$arguments);
    }
}
