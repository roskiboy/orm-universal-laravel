<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\Exceptions\RelationException;

/**
 * Registry of relation definitions per model/table. Third parties can register
 * relations and optimizers without touching the core.
 *
 * @api public
 */
final class RelationRegistry
{
    /** @var array<string, array<string, RelationDefinition>> model => name => definition */
    private array $relations = [];

    public function register(string $modelOrTable, RelationDefinition $definition): void
    {
        $this->relations[$modelOrTable][$definition->name()] = $definition;
    }

    public function has(string $modelOrTable, string $name): bool
    {
        return isset($this->relations[$modelOrTable][$name]);
    }

    public function get(string $modelOrTable, string $name): RelationDefinition
    {
        return $this->relations[$modelOrTable][$name]
            ?? throw new RelationException('Unknown relation ['.$name.'] for ['.$modelOrTable.'].');
    }

    /**
     * @return array<string, RelationDefinition>
     */
    public function for(string $modelOrTable): array
    {
        return $this->relations[$modelOrTable] ?? [];
    }

    /**
     * @return list<string>
     */
    public function names(string $modelOrTable): array
    {
        return array_keys($this->relations[$modelOrTable] ?? []);
    }

    public function graph(string $modelOrTable): RelationGraph
    {
        $graph = new RelationGraph($modelOrTable);

        foreach ($this->for($modelOrTable) as $name => $definition) {
            $graph->add($name, $definition->toMetadata());
        }

        return $graph;
    }
}
