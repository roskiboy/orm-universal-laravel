<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\RelationDefinitionInterface;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

/**
 * First class relation metadata. Relations are never degraded to plain strings.
 *
 * @api public
 */
final class RelationMetadata implements RelationDefinitionInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly TableIdentifier $relatedTable,
        private readonly string $foreignKey,
        private readonly string $localKey = 'id',
        private readonly bool $toMany = false,
        private readonly array $metadata = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function relatedTable(): TableIdentifier
    {
        return $this->relatedTable;
    }

    public function foreignKey(): string
    {
        return $this->foreignKey;
    }

    public function localKey(): string
    {
        return $this->localKey;
    }

    public function isToMany(): bool
    {
        return $this->toMany;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
