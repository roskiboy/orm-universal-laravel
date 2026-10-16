<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Schema;

use OrmUniversal\Contracts\SchemaInspectorInterface;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\SchemaSnapshot;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Execution\QueryExecutor;

/**
 * Base inspector: runs the dialect queries and assembles definitions.
 *
 * @api public
 */
abstract class AbstractSchemaInspector implements SchemaInspectorInterface
{
    public function __construct(
        protected readonly QueryExecutor $executor,
    ) {}

    public function table(TableIdentifier $table): ?TableDefinition
    {
        foreach ($this->tables($table->schema) as $name) {
            if (strcasecmp($name, $table->name) === 0) {
                return new TableDefinition(
                    name: $name,
                    schema: $table->schema,
                    columns: $this->columns($table),
                    indexes: $this->indexes($table),
                    foreignKeys: $this->foreignKeys($table),
                );
            }
        }

        return null;
    }

    public function snapshot(?string $schema = null): SchemaSnapshot
    {
        $tables = [];
        foreach ($this->tables($schema) as $name) {
            $identifier = new TableIdentifier($name, $schema);
            $tables[$name] = new TableDefinition(
                name: $name,
                schema: $schema,
                columns: $this->columns($identifier),
                indexes: $this->indexes($identifier),
                foreignKeys: $this->foreignKeys($identifier),
            );
        }

        return new SchemaSnapshot(
            tables: $tables,
            dialect: $this->executor->dialect()->vendor()->value,
            serverVersion: $this->executor->connection()->serverVersion(),
            generatedAt: date('c'),
        );
    }

    /**
     * @return list<string>
     */
    abstract public function tables(?string $schema = null): array;

    /**
     * @return list<ColumnDefinition>
     */
    abstract public function columns(TableIdentifier $table): array;

    /**
     * @return list<IndexDefinition>
     */
    abstract public function indexes(TableIdentifier $table): array;

    /**
     * @return list<ForeignKeyDefinition>
     */
    abstract public function foreignKeys(TableIdentifier $table): array;
}
