<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Schema;

use OrmUniversal\Contracts\Enums\IndexType;
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\RawQuery;

/**
 * MySQL / MariaDB inspector over information_schema.
 *
 * @api public
 */
final class MySqlSchemaInspector extends AbstractSchemaInspector
{
    /**
     * @return list<string>
     */
    public function tables(?string $schema = null): array
    {
        $sql = 'select table_name as name from information_schema.tables where table_type = ? and table_schema = coalesce(?, database()) order by table_name';
        $rows = $this->executor->rows(new RawQuery($sql, ['BASE TABLE', $schema]));

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(TableIdentifier $table): array
    {
        $sql = 'select column_name, column_type, data_type, is_nullable, column_default, extra, character_maximum_length, numeric_precision, numeric_scale, column_comment, generation_expression '
            .'from information_schema.columns where table_schema = coalesce(?, database()) and table_name = ? order by ordinal_position';
        $rows = $this->executor->rows(new RawQuery($sql, [$table->schema, $table->name]));

        return array_map(static function (array $row): ColumnDefinition {
            $extra = strtolower((string) ($row['extra'] ?? ''));

            return new ColumnDefinition(
                name: (string) $row['column_name'],
                databaseType: (string) ($row['column_type'] ?? $row['data_type']),
                kind: self::kindFor((string) $row['data_type']),
                nullable: strtoupper((string) $row['is_nullable']) === 'YES',
                default: $row['column_default'],
                autoIncrement: str_contains($extra, 'auto_increment'),
                unsigned: str_contains((string) ($row['column_type'] ?? ''), 'unsigned'),
                length: $row['character_maximum_length'] === null ? null : (int) $row['character_maximum_length'],
                precision: $row['numeric_precision'] === null ? null : (int) $row['numeric_precision'],
                scale: $row['numeric_scale'] === null ? null : (int) $row['numeric_scale'],
                comment: $row['column_comment'] === '' ? null : (string) $row['column_comment'],
                generatedExpression: ($row['generation_expression'] ?? '') !== '' ? (string) $row['generation_expression'] : null,
                generatedStored: str_contains($extra, 'stored'),
            );
        }, $rows);
    }

    /**
     * @return list<IndexDefinition>
     */
    public function indexes(TableIdentifier $table): array
    {
        $sql = 'select index_name, non_unique, index_type, column_name, seq_in_index from information_schema.statistics '
            .'where table_schema = coalesce(?, database()) and table_name = ? order by index_name, seq_in_index';
        $rows = $this->executor->rows(new RawQuery($sql, [$table->schema, $table->name]));

        $grouped = [];
        foreach ($rows as $row) {
            $name = (string) $row['index_name'];
            $grouped[$name]['columns'][] = (string) $row['column_name'];
            $grouped[$name]['unique'] = ((int) $row['non_unique']) === 0;
            $grouped[$name]['primary'] = $name === 'PRIMARY';
            $grouped[$name]['type'] = strtolower((string) $row['index_type']);
        }

        $indexes = [];
        foreach ($grouped as $name => $data) {
            $indexes[] = new IndexDefinition(
                name: $name,
                columns: $data['columns'],
                unique: $data['unique'],
                primary: $data['primary'],
                type: str_contains($data['type'], 'fulltext')
                    ? IndexType::FullText
                    : (str_contains($data['type'], 'spatial') ? IndexType::Spatial : IndexType::BTree),
            );
        }

        return $indexes;
    }

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function foreignKeys(TableIdentifier $table): array
    {
        $sql = 'select k.constraint_name, k.column_name, k.referenced_table_name, k.referenced_column_name, r.update_rule, r.delete_rule '
            .'from information_schema.key_column_usage k '
            .'join information_schema.referential_constraints r on r.constraint_schema = k.constraint_schema and r.constraint_name = k.constraint_name '
            .'where k.table_schema = coalesce(?, database()) and k.table_name = ? and k.referenced_table_name is not null '
            .'order by k.constraint_name, k.ordinal_position';
        $rows = $this->executor->rows(new RawQuery($sql, [$table->schema, $table->name]));

        $grouped = [];
        foreach ($rows as $row) {
            $name = (string) $row['constraint_name'];
            $grouped[$name]['columns'][] = (string) $row['column_name'];
            $grouped[$name]['foreign_columns'][] = (string) $row['referenced_column_name'];
            $grouped[$name]['foreign_table'] = (string) $row['referenced_table_name'];
            $grouped[$name]['on_update'] = (string) $row['update_rule'];
            $grouped[$name]['on_delete'] = (string) $row['delete_rule'];
        }

        $keys = [];
        foreach ($grouped as $name => $data) {
            $keys[] = new ForeignKeyDefinition(
                name: $name,
                columns: $data['columns'],
                foreignTable: $data['foreign_table'],
                foreignColumns: $data['foreign_columns'],
                onDelete: $data['on_delete'],
                onUpdate: $data['on_update'],
            );
        }

        return $keys;
    }

    private static function kindFor(string $dataType): TypeKind
    {
        return match (strtolower($dataType)) {
            'smallint', 'mediumint', 'int', 'integer', 'tinyint' => TypeKind::Integer,
            'bigint' => TypeKind::BigInteger,
            'decimal', 'numeric' => TypeKind::Decimal,
            'float', 'double' => TypeKind::Float,
            'tinyint(1)', 'boolean', 'bool' => TypeKind::Boolean,
            'date' => TypeKind::Date,
            'time' => TypeKind::Time,
            'datetime' => TypeKind::DateTime,
            'timestamp' => TypeKind::Timestamp,
            'blob', 'mediumblob', 'longblob', 'tinyblob' => TypeKind::Blob,
            'binary', 'varbinary' => TypeKind::Binary,
            'json' => TypeKind::Json,
            'enum' => TypeKind::Enum,
            'geometry', 'point', 'linestring', 'polygon' => TypeKind::Geometry,
            'text', 'mediumtext', 'longtext', 'tinytext' => TypeKind::Text,
            default => TypeKind::String,
        };
    }
}
