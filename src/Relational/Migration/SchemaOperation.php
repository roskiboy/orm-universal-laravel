<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Migration;

use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;

/**
 * Dialect independent schema operation (create/drop/alter/rename/truncate).
 *
 * @api public
 */
final class SchemaOperation implements SchemaOperationInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private readonly string $name,
        private readonly array $payload = [],
    ) {}

    public static function createTable(TableDefinition $table, bool $ifNotExists = false): self
    {
        return new self('create_table', ['table' => $table, 'if_not_exists' => $ifNotExists]);
    }

    public static function dropTable(string $table, bool $ifExists = true): self
    {
        return new self('drop_table', ['table' => $table, 'if_exists' => $ifExists]);
    }

    /**
     * @param  list<ColumnDefinition>  $columns
     */
    public static function addColumns(string $table, array $columns): self
    {
        return new self('add_columns', ['table' => $table, 'columns' => $columns]);
    }

    /**
     * @param  list<string>  $columns
     */
    public static function dropColumns(string $table, array $columns): self
    {
        return new self('drop_columns', ['table' => $table, 'columns' => $columns]);
    }

    public static function addIndex(string $table, IndexDefinition $index): self
    {
        return new self('add_index', ['table' => $table, 'index' => $index]);
    }

    public static function dropIndex(string $table, string $index): self
    {
        return new self('drop_index', ['table' => $table, 'index' => $index]);
    }

    public static function addForeignKey(string $table, ForeignKeyDefinition $key): self
    {
        return new self('add_foreign_key', ['table' => $table, 'foreign_key' => $key]);
    }

    public static function dropForeignKey(string $table, string $name): self
    {
        return new self('drop_foreign_key', ['table' => $table, 'foreign_key' => $name]);
    }

    public static function renameTable(string $from, string $to): self
    {
        return new self('rename_table', ['from' => $from, 'to' => $to]);
    }

    public static function truncateTable(string $table): self
    {
        return new self('truncate_table', ['table' => $table]);
    }

    public function operationName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function table(): string|TableDefinition|null
    {
        return $this->payload['table'] ?? null;
    }
}
