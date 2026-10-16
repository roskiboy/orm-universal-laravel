<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Migration;

use OrmUniversal\Contracts\Exceptions\MigrationException;
use OrmUniversal\Contracts\MigrationCompilerInterface;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * DDL compiler: migration/schema AST -> dialect SQL. Destructive operations are
 * compiled but the runner applies the safety policy.
 *
 * @api public
 */
final class Compiler implements MigrationCompilerInterface
{
    public function __construct(
        private readonly AbstractDialect $dialect,
    ) {}

    /**
     * @return list<CompiledQuery>
     */
    public function compile(SchemaOperationInterface $operation): array
    {
        if (! $operation instanceof SchemaOperation) {
            throw new MigrationException('Unsupported schema operation ['.$operation->operationName().'].');
        }

        $payload = $operation->payload();

        return match ($operation->operationName()) {
            'create_table' => $this->createTable($payload['table'], (bool) $payload['if_not_exists']),
            'drop_table' => [new CompiledQuery(
                'drop table '.($payload['if_exists'] ? 'if exists ' : '').$this->dialect->quoteIdentifier((string) $payload['table']),
            )],
            'rename_table' => [new CompiledQuery(
                $this->dialect->vendor()->value === 'oracle'
                    ? 'alter table '.$this->dialect->quoteIdentifier((string) $payload['from']).' rename to '.$this->dialect->quoteIdentifier((string) $payload['to'])
                    : 'rename table '.$this->dialect->quoteIdentifier((string) $payload['from']).' to '.$this->dialect->quoteIdentifier((string) $payload['to']),
            )],
            'truncate_table' => [new CompiledQuery('truncate table '.$this->dialect->quoteIdentifier((string) $payload['table']))],
            'add_columns' => $this->alterColumns((string) $payload['table'], $payload['columns'], false),
            'drop_columns' => $this->alterColumns((string) $payload['table'], $payload['columns'], true),
            'add_index' => [$this->createIndex((string) $payload['table'], $payload['index'])],
            'drop_index' => [$this->dropIndex((string) $payload['table'], (string) $payload['index'])],
            'add_foreign_key' => [$this->addForeignKey((string) $payload['table'], $payload['foreign_key'])],
            'drop_foreign_key' => [$this->dropForeignKey((string) $payload['table'], (string) $payload['foreign_key'])],
            default => throw new MigrationException('Unsupported schema operation ['.$operation->operationName().'].'),
        };
    }

    public function supports(SchemaOperationInterface $operation): bool
    {
        if (! $operation instanceof SchemaOperation) {
            return false;
        }

        if ($this->dialect->vendor()->value === 'sqlite') {
            return in_array($operation->operationName(), [
                'create_table', 'drop_table', 'rename_table', 'add_columns', 'drop_columns', 'add_index', 'drop_index',
            ], true);
        }

        return true;
    }

    /**
     * @return list<CompiledQuery>
     */
    public function createTable(TableDefinition $table, bool $ifNotExists = false): array
    {
        $vendor = $this->dialect->vendor()->value;
        $parts = [];

        foreach ($table->columns as $column) {
            $parts[] = $this->columnDefinition($column);
        }

        $primary = $table->options['primary'] ?? null;
        if (is_array($primary) && $primary !== []) {
            $parts[] = 'primary key ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $primary)).')';
        }

        foreach ($table->foreignKeys as $key) {
            $parts[] = $this->foreignKeyClause($key);
        }

        $sql = 'create table '.($ifNotExists ? 'if not exists ' : '').$this->dialect->quoteIdentifier($table->name)
            .' ('.implode(', ', $parts).')';

        if ($vendor === 'mysql' || $vendor === 'mariadb') {
            $engine = $table->options['engine'] ?? null;
            $charset = $table->options['charset'] ?? null;
            $sql .= $engine !== null ? ' engine='.$engine : '';
            $sql .= $charset !== null ? ' default charset='.$charset : '';
        }

        $statements = [new CompiledQuery($sql)];

        foreach ($table->indexes as $index) {
            if ($index->primary) {
                continue;
            }

            $statements[] = $this->createIndex($table->name, $index);
        }

        return $statements;
    }

    public function columnDefinition(ColumnDefinition $column): string
    {
        $vendor = $this->dialect->vendor()->value;
        $type = $column->databaseType !== '' ? $column->databaseType : $this->fallbackType($column);
        $sql = $this->dialect->quoteIdentifier($column->name).' '.$type;

        if ($column->unsigned && ($vendor === 'mysql' || $vendor === 'mariadb') && ! str_contains(strtolower($type), 'unsigned')) {
            $sql .= ' unsigned';
        }

        $sql .= $column->nullable ? ' null' : ' not null';

        if ($column->autoIncrement) {
            $sql .= match ($vendor) {
                'mysql', 'mariadb' => ' auto_increment',
                'sqlite' => '',
                'sqlserver' => ' identity(1,1)',
                'oracle' => ' generated by default on null as identity',
                default => ' generated by default as identity',
            };
        }

        if ($column->default !== null) {
            $sql .= ' default '.$this->renderDefault($column);
        }

        if ($column->generatedExpression !== null) {
            $sql .= ' generated always as ('.$column->generatedExpression.')'.($column->generatedStored ? ' stored' : ' virtual');
        }

        return $sql;
    }

    /**
     * @param  list<ColumnDefinition|string>  $columns
     * @return list<CompiledQuery>
     */
    private function alterColumns(string $table, array $columns, bool $drop): array
    {
        $vendor = $this->dialect->vendor()->value;
        $statements = [];

        foreach ($columns as $column) {
            if ($drop) {
                $name = is_string($column) ? $column : $column->name;
                $prefix = $vendor === 'sqlserver' ? 'alter table '.$this->dialect->quoteIdentifier($table).' drop column ' : 'alter table '.$this->dialect->quoteIdentifier($table).' drop column ';
                $statements[] = new CompiledQuery($prefix.$this->dialect->quoteIdentifier($name));

                continue;
            }

            $statements[] = new CompiledQuery(
                'alter table '.$this->dialect->quoteIdentifier($table).' add column '.$this->columnDefinition($column),
            );
        }

        return $statements;
    }

    private function createIndex(string $table, IndexDefinition $index): CompiledQuery
    {
        $vendor = $this->dialect->vendor()->value;
        $unique = $index->unique ? 'unique ' : '';
        $using = $index->using !== null && ($vendor === 'postgresql' || $vendor === 'mysql' || $vendor === 'mariadb') ? ' using '.$index->using : '';
        $where = $index->where !== null
            ? ($vendor === 'postgresql' || $vendor === 'sqlite' ? ' where '.$index->where : '')
            : '';

        $sql = 'create '.$unique.'index '.$this->dialect->quoteIdentifier($index->name)
            .' on '.$this->dialect->quoteIdentifier($table)
            .$using
            .' ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $index->columns)).')'
            .$where;

        if ($index->where !== null && $where === '' && ($vendor === 'mysql' || $vendor === 'mariadb')) {
            throw new MigrationException('MySQL does not support partial indexes (index ['.$index->name.']).');
        }

        return new CompiledQuery($sql);
    }

    private function dropIndex(string $table, string $index): CompiledQuery
    {
        $vendor = $this->dialect->vendor()->value;

        return match ($vendor) {
            'sqlserver' => new CompiledQuery('drop index '.$this->dialect->quoteIdentifier($index).' on '.$this->dialect->quoteIdentifier($table)),
            'sqlite' => new CompiledQuery('drop index '.$this->dialect->quoteIdentifier($index)),
            'oracle' => new CompiledQuery('drop index '.$this->dialect->quoteIdentifier($index)),
            default => new CompiledQuery('drop index '.$this->dialect->quoteIdentifier($table).'.'.$this->dialect->quoteIdentifier($index)),
        };
    }

    private function addForeignKey(string $table, ForeignKeyDefinition $key): CompiledQuery
    {
        if ($this->dialect->vendor()->value === 'sqlite') {
            throw new MigrationException(
                'SQLite cannot add a foreign key to an existing table; recreate the table with the constraint. Index: '.$key->name,
            );
        }

        return new CompiledQuery(
            'alter table '.$this->dialect->quoteIdentifier($table).' add constraint '.$this->dialect->quoteIdentifier($key->name).' '.$this->foreignKeyClause($key),
        );
    }

    private function dropForeignKey(string $table, string $name): CompiledQuery
    {
        $vendor = $this->dialect->vendor()->value;

        return match ($vendor) {
            'sqlserver' => new CompiledQuery('alter table '.$this->dialect->quoteIdentifier($table).' drop constraint '.$this->dialect->quoteIdentifier($name)),
            'oracle' => new CompiledQuery('alter table '.$this->dialect->quoteIdentifier($table).' drop constraint '.$this->dialect->quoteIdentifier($name)),
            default => new CompiledQuery('alter table '.$this->dialect->quoteIdentifier($table).' drop foreign key '.$this->dialect->quoteIdentifier($name)),
        };
    }

    private function foreignKeyClause(ForeignKeyDefinition $key): string
    {
        $sql = 'foreign key ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $key->columns)).')'
            .' references '.$this->dialect->quoteIdentifier($key->foreignTable)
            .' ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $key->foreignColumns)).')';

        if ($key->onDelete !== null) {
            $sql .= ' on delete '.strtoupper(str_replace('_', ' ', $key->onDelete));
        }

        if ($key->onUpdate !== null) {
            $sql .= ' on update '.strtoupper(str_replace('_', ' ', $key->onUpdate));
        }

        return $sql;
    }

    private function renderDefault(ColumnDefinition $column): string
    {
        $default = $column->default;

        if (is_bool($default)) {
            return $this->dialect->booleanLiteral($default);
        }

        if ($default === null) {
            return 'null';
        }

        if (is_int($default) || is_float($default)) {
            return (string) $default;
        }

        $text = (string) $default;
        $upper = strtoupper(trim($text));

        // Recognized SQL expressions are preserved; everything else is quoted.
        if (in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME', 'NULL'], true)
            || str_starts_with($upper, 'NEXTVAL(')
            || str_starts_with($upper, 'GEN_RANDOM')
            || str_starts_with($upper, '(')
        ) {
            return $text;
        }

        return $this->dialect->escapeStringLiteral($text);
    }

    private function fallbackType(ColumnDefinition $column): string
    {
        return match ($column->kind->value) {
            'integer' => 'integer',
            'big_integer' => 'bigint',
            'decimal' => 'decimal('.($column->precision ?? 10).','.($column->scale ?? 2).')',
            'float' => 'double precision',
            'boolean' => $this->dialect->vendor()->value === 'sqlite' ? 'integer' : 'boolean',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'json', 'jsonb', 'array', 'object' => 'text',
            'uuid' => 'char(36)',
            'ulid' => 'char(26)',
            'vector' => 'text',
            default => 'varchar('.($column->length ?? 255).')',
        };
    }
}
