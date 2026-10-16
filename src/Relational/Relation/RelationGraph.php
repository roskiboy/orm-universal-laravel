<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\Exceptions\RelationException;

/**
 * Relation tree used for eager loading planning:
 *
 * Customer -> Orders -> Products -> Category.
 *
 * @api public
 */
final class RelationGraph
{
    /** @var array<string, self> */
    private array $children = [];

    /**
     * @param  list<string>  $path
     */
    public function __construct(
        private readonly string $name = 'root',
        private readonly ?RelationMetadata $relation = null,
        private array|string $path = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function relation(): ?RelationMetadata
    {
        return $this->relation;
    }

    /**
     * Add a dotted relation path such as "orders.products.category".
     */
    public function add(string $path, ?RelationMetadata $metadata = null): self
    {
        $segments = array_values(array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            throw new RelationException('Empty relation path.');
        }

        $node = $this;
        foreach ($segments as $index => $segment) {
            if (! isset($node->children[$segment])) {
                $node->children[$segment] = new self($segment, $index === count($segments) - 1 ? $metadata : null);
            }
            $node = $node->children[$segment];
        }

        return $this;
    }

    /**
     * @return array<string, self>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * Flattened list of dotted paths in insertion order.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        $paths = [];
        $this->collect('', $paths);

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     */
    private function collect(string $prefix, array &$paths): void
    {
        foreach ($this->children as $name => $child) {
            $path = $prefix === '' ? $name : $prefix.'.'.$name;
            if ($child->relation !== null) {
                $paths[] = $path;
            }
            $child->collect($path, $paths);
        }
    }
}
