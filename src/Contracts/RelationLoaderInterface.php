<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Loads a relation for a set of parent rows, batching queries to avoid N+1.
 *
 * @api public
 */
interface RelationLoaderInterface
{
    public function supports(RelationDefinitionInterface $definition): bool;

    /**
     * @param  iterable<int, mixed>  $parents
     * @return array<int|string, mixed> related results keyed by parent key
     */
    public function load(iterable $parents, RelationDefinitionInterface $definition): array;
}
