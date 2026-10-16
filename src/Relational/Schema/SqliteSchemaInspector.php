<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Schema;

use OrmUniversal\Contracts\Enums\IndexType;
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\Exceptions\SecurityException;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\RawQuery;

/**
 * SQLite inspector based on sqlite_master and PRAGMA statements.
 *
 * @api public
 */
final class SqliteSchemaInspector extends AbstractSchemaInspector
{
    /**
     * @return list<string>
     */
    public function tables(?string $schema = null): array
    {
        $rows = $this->executor->rows(new RawQuery(
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name",
        ));

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(TableIdentifier $table): array
    {
        $rows = $this->executor->rows(new RawQuery('pragma table_info('.$this->quote($table->name).')'));

        return array_map(static function (array $row): ColumnDefinition {
            $type = strtolower((string) ($row['type'] ?? ''));

            return new ColumnDefinition(
                name: (string) $row['name'],
                databaseType: $type === '' ? 'text' : $type,
                kind: self::kindFor($type),
                nullable: ((int) ($row['notnull'] ?? 0)) === 0,
                default: $row['dflt_value'] ?? null,
                autoIncrement: ((int) ($row['pk'] ?? 0)) > 0 && $type === 'integer',
            );
        }, $rows);
    }

    /**
     * @return list<IndexDefinition>
     */
    public function indexes(TableIdentifier $table): array
    {
        $rows = $this->executor->rows(new RawQuery('pragma index_list('.$this->quote($table->name).')'));
        $indexes = [];

        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if (str_starts_with($name, 'sqlite_autoindex')) {
                continue;
            }

            $columns = $this->executor->rows(new RawQuery('pragma index_info('.$this->quote($name).')'));
            $indexes[] = new IndexDefinition(
                name: $name,
                columns: array_map(static fn (array $column): string => (string) $column['name'], $columns),
                unique: ((int) ($row['unique'] ?? 0)) === 1,
                type: str_contains((string) ($row['origin'] ?? ''), 'pk') ? IndexType::BTree : IndexType::BTree,
            );
        }

        return $indexes;
    }

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function foreignKeys(TableIdentifier $table): array
    {
        $rows = $this->executor->rows(new RawQuery('pragma foreign_key_list('.$this->quote($table->name).')'));

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $grouped[$id]['columns'][] = (string) $row['from'];
            $grouped[$id]['foreign_columns'][] = (string) $row['to'];
            $grouped[$id]['foreign_table'] = (string) $row['table'];
            $grouped[$id]['on_delete'] = (string) ($row['on_delete'] ?? 'NO ACTION');
            $grouped[$id]['on_update'] = (string) ($row['on_update'] ?? 'NO ACTION');
        }

        $keys = [];
        foreach ($grouped as $id => $data) {
            $keys[] = new ForeignKeyDefinition(
                name: 'fk_'.$table->name.'_'.$id,
                columns: $data['columns'],
                foreignTable: $data['foreign_table'],
                foreignColumns: $data['foreign_columns'],
                onDelete: $data['on_delete'],
                onUpdate: $data['on_update'],
            );
        }

        return $keys;
    }

    private function quote(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new SecurityException('Unsafe SQLite identifier ['.$identifier.'] in PRAGMA statement.');
        }

        return '"'.$identifier.'"';
    }

    private static function kindFor(string $type): TypeKind
    {
        return match (true) {
            str_contains($type, 'int') => TypeKind::Integer,
            str_contains($type, 'char'), str_contains($type, 'clob'), str_contains($type, 'text') => TypeKind::Text,
            str_contains($type, 'blob') => TypeKind::Blob,
            str_contains($type, 'real'), str_contains($type, 'floa'), str_contains($type, 'doub') => TypeKind::Float,
            str_contains($type, 'dec'), str_contains($type, 'num') => TypeKind::Decimal,
            str_contains($type, 'bool') => TypeKind::Boolean,
            str_contains($type, 'date'), str_contains($type, 'time') => TypeKind::DateTime,
            default => TypeKind::Custom,
        };
    }
}
