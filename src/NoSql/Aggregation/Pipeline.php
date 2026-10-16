<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Aggregation;

/**
 * Aggregation pipeline, independent from SQL semantics.
 *
 * @api public
 */
final class Pipeline implements \JsonSerializable
{
    /** @var list<array<string, mixed>> */
    private array $stages = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    public function match(array $filter): self
    {
        $this->stages[] = ['$match' => $filter];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function group(array $spec): self
    {
        $this->stages[] = ['$group' => $spec];

        return $this;
    }

    /**
     * @param  array<string, 1|-1>  $fields
     */
    public function sort(array $fields): self
    {
        $this->stages[] = ['$sort' => $fields];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->stages[] = ['$limit' => max(0, $limit)];

        return $this;
    }

    public function skip(int $skip): self
    {
        $this->stages[] = ['$skip' => max(0, $skip)];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function project(array $spec): self
    {
        $this->stages[] = ['$project' => $spec];

        return $this;
    }

    public function unwind(string $path): self
    {
        $this->stages[] = ['$unwind' => $path];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $stage
     */
    public function stage(array $stage): self
    {
        $this->stages[] = $stage;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->stages;
    }
}
