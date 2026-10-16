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
 * Oracle inspector over USER_* / ALL_* data dictionary views.
 *
 * @api public
 */
final class OracleSchemaInspector extends AbstractSchemaInspector
{
    /**
     * @return list<string>
     */
    public function tables(?string $schema = null): array
    {
        $rows = $this->executor->rows(new RawQuery(
            'select table_name as name from all_tables where owner = coalesce(?, sys_context(\'userenv\', \'current_schema\')) and nested = \'NO\' order by table_name',
            [$schema !== null ? strtoupper($schema) : null],
        ));

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(TableIdentifier $table): array
    {
        $rows = $this->executor->rows(new RawQuery(
            'select column_name, data_type, nullable, data_default, char_length, data_precision, data_scale, identity_column, virtual_column '
            .'from all_tab_cols where owner = coalesce(?, sys_context(\'userenv\', \'current_schema\')) and table_name = ? and hidden_column = \'NO\' order by column_id',
            [$table->schema !== null ? strtoupper($table->schema) : null, strtoupper($table->name)],
        ));

        return array_map(static function (array $row): ColumnDefinition {
            $type = strtolower((string) $row['data_type']);

            return new ColumnDefinition(
                name: (string) $row['column_name'],
                databaseType: $type,
                kind: self::kindFor($type),
                nullable: strtoupper((string) $row['nullable']) === 'Y',
                default: $row['data_default'] === null ? null : trim((string) $row['data_default']),
                autoIncrement: strtoupper((string) ($row['identity_column'] ?? 'NO')) === 'YES',
                length: $row['char_length'] === null ? null : (int) $row['char_length'],
                precision: $row['data_precision'] === null ? null : (int) $row['data_precision'],
                scale: $row['data_scale'] === null ? null : (int) $row['data_scale'],
                generatedExpression: strtoupper((string) ($row['virtual_column'] ?? 'NO')) === 'YES' ? '<virtual>' : null,
            );
        }, $rows);
    }

    /**
     * @return list<IndexDefinition>
     */
    public function indexes(TableIdentifier $table): array
    {
        $sql = 'select i.index_name, i.uniqueness, i.index_type, c.column_name, c.column_position '
            .'from all_indexes i join all_ind_columns c on c.index_owner = i.owner and c.index_name = i.index_name '
            .'where i.table_owner = coalesce(?, sys_context(\'userenv\', \'current_schema\')) and i.table_name = ? order by i.index_name, c.column_position';
        $rows = $this->executor->rows(new RawQuery($sql, [
            $table->schema !== null ? strtoupper($table->schema) : null,
            strtoupper($table->name),
        ]));

        $grouped = [];
        foreach ($rows as $row) {
            $name = (string) $row['index_name'];
            $grouped[$name]['columns'][] = (string) $row['column_name'];
            $grouped[$name]['unique'] = strtoupper((string) $row['uniqueness']) === 'UNIQUE';
            $grouped[$name]['type'] = (string) $row['index_type'];
        }

        $indexes = [];
        foreach ($grouped as $name => $data) {
            $indexes[] = new IndexDefinition(
                name: $name,
                columns: $data['columns'],
                unique: $data['unique'],
                primary: str_contains(strtoupper($data['type']), 'NORMAL') && $name === strtoupper($table->name).'_PK',
                type: IndexType::BTree,
                using: $data['type'],
            );
        }

        return $indexes;
    }

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function foreignKeys(TableIdentifier $table): array
    {
        $sql = 'select c.constraint_name, cc.column_name, r.table_name as foreign_table, rcc.column_name as foreign_column, c.delete_rule '
            .'from all_constraints c '
            .'join all_cons_columns cc on cc.owner = c.owner and cc.constraint_name = c.constraint_name '
            .'join all_constraints r on r.owner = c.r_owner and r.constraint_name = c.r_constraint_name '
            .'join all_cons_columns rcc on rcc.owner = r.owner and rcc.constraint_name = r.constraint_name and rcc.position = cc.position '
            ."where c.constraint_type = 'R' and c.owner = coalesce(?, sys_context('userenv', 'current_schema')) and c.table_name = ? order by c.constraint_name, cc.position";
        $rows = $this->executor->rows(new RawQuery($sql, [
            $table->schema !== null ? strtoupper($table->schema) : null,
            strtoupper($table->name),
        ]));

        $grouped = [];
        foreach ($rows as $row) {
            $name = (string) $row['constraint_name'];
            $grouped[$name]['columns'][] = (string) $row['column_name'];
            $grouped[$name]['foreign_columns'][] = (string) $row['foreign_column'];
            $grouped[$name]['foreign_table'] = (string) $row['foreign_table'];
            $grouped[$name]['on_delete'] = (string) ($row['delete_rule'] ?? 'NO ACTION');
        }

        $keys = [];
        foreach ($grouped as $name => $data) {
            $keys[] = new ForeignKeyDefinition(
                name: $name,
                columns: $data['columns'],
                foreignTable: $data['foreign_table'],
                foreignColumns: $data['foreign_columns'],
                onDelete: $data['on_delete'],
            );
        }

        return $keys;
    }

    private static function kindFor(string $type): TypeKind
    {
        return match (true) {
            $type === 'number' => TypeKind::Decimal,
            $type === 'float' || $type === 'binary_float' || $type === 'binary_double' => TypeKind::Float,
            str_starts_with($type, 'timestamp') => TypeKind::Timestamp,
            $type === 'date' => TypeKind::DateTime,
            $type === 'clob' || $type === 'nclob' => TypeKind::Text,
            $type === 'blob' => TypeKind::Blob,
            $type === 'raw' || $type === 'long raw' => TypeKind::Binary,
            str_starts_with($type, 'varchar2') || $type === 'char' => TypeKind::String,
            $type === 'json' => TypeKind::Json,
            $type === 'vector' => TypeKind::Vector,
            $type === 'sdo_geometry' => TypeKind::Geometry,
            default => TypeKind::Custom,
        };
    }
}
