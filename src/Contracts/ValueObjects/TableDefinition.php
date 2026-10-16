<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Full table definition produced by schema inspection or by a schema builder.
 *
 * @api public
 */
final class TableDefinition
{
    /**
     * @param  list<ColumnDefinition>  $columns
     * @param  list<IndexDefinition>  $indexes
     * @param  list<ForeignKeyDefinition>  $foreignKeys
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $schema = null,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
        public array $options = [],
    ) {}

    public function column(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if (strcasecmp($column->name, $name) === 0) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'schema' => $this->schema,
            'columns' => array_map(static fn (ColumnDefinition $column): array => $column->toArray(), $this->columns),
            'indexes' => array_map(static fn (IndexDefinition $index): array => $index->toArray(), $this->indexes),
            'foreign_keys' => array_map(static fn (ForeignKeyDefinition $key): array => $key->toArray(), $this->foreignKeys),
            'options' => $this->options,
        ];
    }
}
