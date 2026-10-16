<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Relation;

use OrmUniversal\Contracts\RelationDefinitionInterface;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

/**
 * Concrete relation definition. Supports every Eloquent relation family plus
 * custom relations; relations are never degraded to plain strings.
 *
 * @api public
 */
final class RelationDefinition implements RelationDefinitionInterface
{
    public const HAS_ONE = 'hasOne';

    public const HAS_MANY = 'hasMany';

    public const BELONGS_TO = 'belongsTo';

    public const BELONGS_TO_MANY = 'belongsToMany';

    public const HAS_ONE_THROUGH = 'hasOneThrough';

    public const HAS_MANY_THROUGH = 'hasManyThrough';

    public const MORPH_ONE = 'morphOne';

    public const MORPH_MANY = 'morphMany';

    public const MORPH_TO = 'morphTo';

    public const MORPH_TO_MANY = 'morphToMany';

    public const MORPHED_BY_MANY = 'morphedByMany';

    public const CUSTOM = 'custom';

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<string>  $pivotColumns
     */
    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly TableIdentifier $relatedTable,
        private readonly string $foreignKey,
        private readonly string $localKey = 'id',
        private readonly bool $toMany = false,
        private readonly array $metadata = [],
        private readonly ?string $pivotTable = null,
        private readonly array $pivotColumns = [],
        private readonly ?string $morphType = null,
    ) {}

    public static function fromMetadata(RelationMetadata $metadata): self
    {
        $data = $metadata->metadata();

        return new self(
            name: $metadata->name(),
            type: $metadata->type(),
            relatedTable: $metadata->relatedTable(),
            foreignKey: $metadata->foreignKey(),
            localKey: $metadata->localKey(),
            toMany: $metadata->isToMany(),
            metadata: $data,
            pivotTable: isset($data['pivot_table']) ? (string) $data['pivot_table'] : null,
            pivotColumns: array_values((array) ($data['pivot_columns'] ?? [])),
            morphType: isset($data['morph_type']) ? (string) $data['morph_type'] : null,
        );
    }

    public static function hasMany(string $name, TableIdentifier $table, string $foreignKey, string $localKey = 'id'): self
    {
        return new self($name, self::HAS_MANY, $table, $foreignKey, $localKey, true);
    }

    public static function hasOne(string $name, TableIdentifier $table, string $foreignKey, string $localKey = 'id'): self
    {
        return new self($name, self::HAS_ONE, $table, $foreignKey, $localKey, false);
    }

    public static function belongsTo(string $name, TableIdentifier $table, string $foreignKey, string $localKey = 'id'): self
    {
        return new self($name, self::BELONGS_TO, $table, $foreignKey, $localKey, false);
    }

    public static function belongsToMany(
        string $name,
        TableIdentifier $relatedTable,
        TableIdentifier $pivotTable,
        string $foreignKey,
        string $relatedKey,
        array $pivotColumns = [],
    ): self {
        return new self(
            name: $name,
            type: self::BELONGS_TO_MANY,
            relatedTable: $relatedTable,
            foreignKey: $foreignKey,
            localKey: 'id',
            toMany: true,
            metadata: [
                'pivot_table' => $pivotTable->name,
                'pivot_columns' => array_merge([$foreignKey, $relatedKey], $pivotColumns),
                'related_key' => $relatedKey,
            ],
            pivotTable: $pivotTable->name,
            pivotColumns: array_merge([$foreignKey, $relatedKey], $pivotColumns),
        );
    }

    public static function morphMany(string $name, TableIdentifier $table, string $foreignKey, string $morphType, string $localKey = 'id'): self
    {
        return new self(
            name: $name,
            type: self::MORPH_MANY,
            relatedTable: $table,
            foreignKey: $foreignKey,
            localKey: $localKey,
            toMany: true,
            metadata: ['morph_type' => $morphType],
            morphType: $morphType,
        );
    }

    public static function morphOne(string $name, TableIdentifier $table, string $foreignKey, string $morphType, string $localKey = 'id'): self
    {
        return new self(
            name: $name,
            type: self::MORPH_ONE,
            relatedTable: $table,
            foreignKey: $foreignKey,
            localKey: $localKey,
            toMany: false,
            metadata: ['morph_type' => $morphType],
            morphType: $morphType,
        );
    }

    /**
     * Immutable metadata merge, used by adapters to enrich inferred relations.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            relatedTable: $this->relatedTable,
            foreignKey: $this->foreignKey,
            localKey: $this->localKey,
            toMany: $this->toMany,
            metadata: array_merge($this->metadata, $metadata),
            pivotTable: $this->pivotTable,
            pivotColumns: $this->pivotColumns,
            morphType: $this->morphType,
        );
    }

    public static function custom(string $name, TableIdentifier $table, string $foreignKey, string $localKey = 'id', bool $toMany = false): self
    {
        return new self($name, self::CUSTOM, $table, $foreignKey, $localKey, $toMany);
    }

    public static function hasOneThrough(
        string $name,
        TableIdentifier $related,
        TableIdentifier $through,
        string $throughForeignKey,
        string $throughLocalKey,
        string $relatedKey,
        string $localKey = 'id',
    ): self {
        return new self($name, self::HAS_ONE_THROUGH, $related, $throughForeignKey, $localKey, false, [
            'through' => $through->name,
            'through_local_key' => $throughLocalKey,
            'related_key' => $relatedKey,
        ]);
    }

    public static function hasManyThrough(
        string $name,
        TableIdentifier $related,
        TableIdentifier $through,
        string $throughForeignKey,
        string $throughLocalKey,
        string $relatedKey,
        string $localKey = 'id',
    ): self {
        return new self($name, self::HAS_MANY_THROUGH, $related, $throughForeignKey, $localKey, true, [
            'through' => $through->name,
            'through_local_key' => $throughLocalKey,
            'related_key' => $relatedKey,
        ]);
    }

    /**
     * @param  list<string>  $pivotColumns
     */
    public static function morphToMany(
        string $name,
        TableIdentifier $related,
        TableIdentifier $pivot,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $morphType,
        string $morphClass,
        bool $inverse = false,
        array $pivotColumns = [],
    ): self {
        return new self(
            name: $name,
            type: $inverse ? self::MORPHED_BY_MANY : self::MORPH_TO_MANY,
            relatedTable: $related,
            foreignKey: $foreignPivotKey,
            localKey: 'id',
            toMany: true,
            metadata: [
                'pivot_table' => $pivot->name,
                'pivot_columns' => array_merge([$foreignPivotKey, $relatedPivotKey], $pivotColumns),
                'related_key' => $relatedPivotKey,
                'morph_type' => $morphType,
                'morph_class' => $morphClass,
                'inverse' => $inverse,
            ],
            pivotTable: $pivot->name,
            pivotColumns: array_merge([$foreignPivotKey, $relatedPivotKey], $pivotColumns),
            morphType: $morphType,
        );
    }

    /**
     * Polymorphic to-one. Query execution requires the explicit morph map
     * (type => table); without it the relation is intentionally not executable.
     *
     * @param  array<string, string>  $morphMap  type => table
     */
    public static function morphTo(
        string $name,
        string $foreignKey,
        string $morphType,
        array $morphMap,
        string $ownerKey = 'id',
    ): self {
        $firstTable = $morphMap === [] ? '__morph__' : (string) reset($morphMap);

        return new self(
            name: $name,
            type: self::MORPH_TO,
            relatedTable: TableIdentifier::fromString($firstTable),
            foreignKey: $foreignKey,
            localKey: $ownerKey,
            toMany: false,
            metadata: [
                'morph_map' => $morphMap,
                'morph_type_column' => $morphType,
                'morph_id_column' => $foreignKey,
                'owner_key' => $ownerKey,
            ],
            morphType: $morphType,
        );
    }

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

    public function pivotTable(): ?string
    {
        return $this->pivotTable;
    }

    /**
     * @return list<string>
     */
    public function pivotColumns(): array
    {
        return $this->pivotColumns;
    }

    public function morphType(): ?string
    {
        return $this->morphType;
    }

    public function usesPivot(): bool
    {
        return $this->pivotTable !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'related_table' => $this->relatedTable->qualifiedName(),
            'foreign_key' => $this->foreignKey,
            'local_key' => $this->localKey,
            'to_many' => $this->toMany,
            'pivot_table' => $this->pivotTable,
            'morph_type' => $this->morphType,
            'metadata' => $this->metadata,
        ];
    }

    public function toMetadata(): RelationMetadata
    {
        return new RelationMetadata(
            name: $this->name,
            type: $this->type,
            relatedTable: $this->relatedTable,
            foreignKey: $this->foreignKey,
            localKey: $this->localKey,
            toMany: $this->toMany,
            metadata: $this->metadata,
        );
    }
}
