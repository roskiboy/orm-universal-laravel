<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Foreign key definition.
 *
 * @api public
 */
final class ForeignKeyDefinition
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly string $foreignTable,
        public readonly array $foreignColumns,
        public readonly ?string $onDelete = null,
        public readonly ?string $onUpdate = null,
        public readonly bool $deferrable = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'foreign_table' => $this->foreignTable,
            'foreign_columns' => $this->foreignColumns,
            'on_delete' => $this->onDelete,
            'on_update' => $this->onUpdate,
            'deferrable' => $this->deferrable,
        ];
    }
}
