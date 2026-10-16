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
 * SQL Server inspector over information_schema and sys catalog views.
 *
 * @api public
 */
final class SqlServerSchemaInspector extends AbstractSchemaInspector
{
    /**
     * @return list<string>
     */
    public function tables(?string $schema = null): array
    {
        $rows = $this->executor->rows(new RawQuery(
            'select table_name as name from information_schema.tables where table_type = ? and table_schema = coalesce(?, schema_name()) order by table_name',
            ['BASE TABLE', $schema],
        ));

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(TableIdentifier $table): array
    {
        $rows = $this->executor->rows(new RawQuery(
            'select c.name as column_name, t.name as data_type, c.is_nullable, c.is_identity, dc.definition as column_default, c.max_length, c.precision, c.scale, c.is_computed '
            .'from sys.columns c join sys.types t on t.user_type_id = c.user_type_id '
            .'left join sys.default_constraints dc on dc.parent_object_id = c.object_id and dc.parent_column_id = c.column_id '
            .'where c.object_id = object_id(?) order by c.column_id',
            [($table->schema !== null ? $table->schema.'.' : '').$table->name],
        ));

        return array_map(static function (array $row): ColumnDefinition {
            $type = strtolower((string) $row['data_type']);
            $maxLength = (int) $row['max_length'];

            return new ColumnDefinition(
                name: (string) $row['column_name'],
                databaseType: $type,
                kind: self::kindFor($type),
                nullable: (bool) $row['is_nullable'],
                default: $row['column_default'],
                autoIncrement: (bool) $row['is_identity'],
                length: in_array($type, ['nvarchar', 'nchar'], true) && $maxLength > 0 ? (int) ($maxLength / 2) : ($maxLength > 0 ? $maxLength : null),
                precision: (int) $row['precision'],
                scale: (int) $row['scale'],
                generatedExpression: (bool) $row['is_computed'] ? '<computed>' : null,
                generatedStored: (bool) $row['is_computed'],
            );
        }, $rows);
    }

    /**
     * @return list<IndexDefinition>
     */
    public function indexes(TableIdentifier $table): array
    {
        $sql = 'select i.name as index_name, i.is_unique, i.is_primary_key, i.type_desc, i.filter_definition, '
            ."string_agg(c.name, ',') within group (order by ic.key_ordinal) as columns "
            .'from sys.indexes i join sys.index_columns ic on ic.object_id = i.object_id and ic.index_id = i.index_id '
            .'join sys.columns c on c.object_id = ic.object_id and c.column_id = ic.column_id '
            .'where i.object_id = object_id(?) and ic.is_included_column = 0 group by i.name, i.is_unique, i.is_primary_key, i.type_desc, i.filter_definition order by i.name';
        $rows = $this->executor->rows(new RawQuery($sql, [($table->schema !== null ? $table->schema.'.' : '').$table->name]));

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[] = new IndexDefinition(
                name: (string) $row['index_name'],
                columns: explode(',', (string) $row['columns']),
                unique: (bool) $row['is_unique'],
                primary: (bool) $row['is_primary_key'],
                type: str_contains(strtolower((string) ($row['type_desc'] ?? '')), 'columnstore')
                    ? IndexType::Bitmap
                    : IndexType::BTree,
                where: $row['filter_definition'] === null ? null : (string) $row['filter_definition'],
            );
        }

        return $indexes;
    }

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function foreignKeys(TableIdentifier $table): array
    {
        $sql = 'select fk.name as constraint_name, pc.name as column_name, rt.name as foreign_table, rc.name as foreign_column, fk.delete_referential_action_desc, fk.update_referential_action_desc '
            .'from sys.foreign_keys fk '
            .'join sys.foreign_key_columns fkc on fkc.constraint_object_id = fk.object_id '
            .'join sys.columns pc on pc.object_id = fkc.parent_object_id and pc.column_id = fkc.parent_column_id '
            .'join sys.columns rc on rc.object_id = fkc.referenced_object_id and rc.column_id = fkc.referenced_column_id '
            .'join sys.objects rt on rt.object_id = fk.referenced_object_id '
            .'where fk.parent_object_id = object_id(?) order by fk.name, fkc.constraint_column_id';
        $rows = $this->executor->rows(new RawQuery($sql, [($table->schema !== null ? $table->schema.'.' : '').$table->name]));

        $grouped = [];
        foreach ($rows as $row) {
            $name = (string) $row['constraint_name'];
            $grouped[$name]['columns'][] = (string) $row['column_name'];
            $grouped[$name]['foreign_columns'][] = (string) $row['foreign_column'];
            $grouped[$name]['foreign_table'] = (string) $row['foreign_table'];
            $grouped[$name]['on_delete'] = (string) $row['delete_referential_action_desc'];
            $grouped[$name]['on_update'] = (string) $row['update_referential_action_desc'];
        }

        $keys = [];
        foreach ($grouped as $name => $data) {
            $keys[] = new ForeignKeyDefinition(
                name: $name,
                columns: $data['columns'],
                foreignTable: $data['foreign_table'],
                foreignColumns: $data['foreign_columns'],
                onDelete: str_replace('_', ' ', $data['on_delete']),
                onUpdate: str_replace('_', ' ', $data['on_update']),
            );
        }

        return $keys;
    }

    private static function kindFor(string $type): TypeKind
    {
        return match ($type) {
            'int', 'smallint', 'tinyint' => TypeKind::Integer,
            'bigint' => TypeKind::BigInteger,
            'decimal', 'numeric', 'money', 'smallmoney' => TypeKind::Decimal,
            'float', 'real' => TypeKind::Float,
            'bit' => TypeKind::Boolean,
            'date' => TypeKind::Date,
            'time' => TypeKind::Time,
            'datetime', 'datetime2', 'smalldatetime' => TypeKind::DateTime,
            'datetimeoffset' => TypeKind::Timestamp,
            'uniqueidentifier' => TypeKind::Uuid,
            'varbinary', 'binary', 'timestamp' => TypeKind::Binary,
            'image' => TypeKind::Blob,
            'xml', 'geometry', 'geography' => TypeKind::Custom,
            'text', 'ntext' => TypeKind::Text,
            default => TypeKind::String,
        };
    }
}
