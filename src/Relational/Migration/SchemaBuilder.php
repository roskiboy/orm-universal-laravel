<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Migration;

use OrmUniversal\Contracts\Exceptions\MigrationException;
use OrmUniversal\Contracts\SchemaBuilderInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Schema builder: compiles schema operations through the dialect migration
 * compiler. Never executes anything by itself.
 *
 * @api public
 */
final class SchemaBuilder implements SchemaBuilderInterface
{
    public function __construct(
        private readonly AbstractDialect $dialect,
    ) {}

    public function create(TableDefinition $table, bool $ifNotExists = false): array
    {
        return $this->compiler()->createTable($table, $ifNotExists);
    }

    public function drop(string $table, array $columns = [], array $indexes = [], array $foreignKeys = []): array
    {
        return [
            ...$this->compile(SchemaOperation::dropTable($table, false)),
            ...$this->compile(SchemaOperation::dropColumns($table, $columns)),
            ...array_map(fn (string $index): CompiledQuery => $this->compile(SchemaOperation::dropIndex($table, $index))[0], $indexes),
        ];
    }

    public function dropIfExists(string $table): array
    {
        return $this->compile(SchemaOperation::dropTable($table, true));
    }

    public function rename(string $from, string $to): array
    {
        return $this->compile(SchemaOperation::renameTable($from, $to));
    }

    public function truncate(string $table): array
    {
        return $this->compile(SchemaOperation::truncateTable($table));
    }

    /**
     * @return list<CompiledQuery>
     */
    public function compile(SchemaOperation $operation): array
    {
        return $this->compiler()->compile($operation);
    }

    private function compiler(): Compiler
    {
        $compiler = $this->dialect->migrationCompiler();

        if (! $compiler instanceof Compiler) {
            throw new MigrationException('The dialect migration compiler is not a relational compiler.');
        }

        return $compiler;
    }
}
