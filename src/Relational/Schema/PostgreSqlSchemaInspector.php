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
 * PostgreSQL inspector over information_schema and pg_catalog.
 *
 * @api public
 */
final class PostgreSqlSchemaInspector extends AbstractSchemaInspector
{
    /**
     * @return list<string>
     */
    public function tables(?string $schema = null): array
    {
        $rows = $this->executor->rows(new RawQuery(
            'select table_name as name from information_schema.tables where table_type = ? and table_schema = coalesce(?, current_schema()) order by table_name',
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
            'select column_name, data_type, udt_name, is_nullable, column_default, character_maximum_length, numeric_precision, numeric_scale, is_identity, is_generated, generation_expression '
            .'from information_schema.columns where table_schema = coalesce(?, current_schema()) and table_name = ? order by ordinal_position',
            [$table->schema, $table->name],
        ));

        return array_map(static function (array $row): ColumnDefinition {
            $type = strtolower((string) ($row['udt_name'] ?? $row['data_type']));
            $default = $row['column_default'];

            return new ColumnDefinition(
                name: (string) $row['column_name'],
                databaseType: $type,
                kind: self::kindFor($type),
                nullable: strtoupper((string) $row['is_nullable']) === 'YES',
                default: $default,
                autoIncrement: strtoupper((string) ($row['is_identity'] ?? 'NO')) === 'YES'
                    || (is_string($default) && str_contains($default, 'nextval(')),
                length: $row['character_maximum_length'] === null ? null : (int) $row['character_maximum_length'],
                precision: $row['numeric_precision'] === null ? null : (int) $row['numeric_precision'],
                scale: $row['numeric_scale'] === null ? null : (int) $row['numeric_scale'],
                generatedExpression: ($row['generation_expression'] ?? '') !== '' ? (string) $row['generation_expression'] : null,
                generatedStored: strtoupper((string) ($row['is_generated'] ?? 'NEVER')) === 'ALWAYS',
            );
        }, $rows);
    }

    /**
     * @return list<IndexDefinition>
     */
    public function indexes(TableIdentifier $table): array
    {
        $sql = 'select i.relname as index_name, ix.indisunique as is_unique, ix.indisprimary as is_primary, '
            ."array_to_string(array(select a.attname from unnest(ix.indkey) with ordinality as k(attnum, ord) join pg_attribute a on a.attrelid = t.oid and a.attnum = k.attnum order by k.ord), ',') as columns, "
            .'am.amname as method from pg_class t '
            .'join pg_index ix on t.oid = ix.indrelid '
            .'join pg_class i on i.oid = ix.indexrelid '
            .'join pg_am am on am.oid = i.relam '
            .'where t.oid = to_regclass(?) order by i.relname';
        $qualified = ($table->schema !== null ? $table->schema.'.' : '').$table->name;
        $rows = $this->executor->rows(new RawQuery($sql, [$qualified]));

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[] = new IndexDefinition(
                name: (string) $row['index_name'],
                columns: array_values(array_filter(explode(',', (string) $row['columns']))),
                unique: (bool) $row['is_unique'],
                primary: (bool) $row['is_primary'],
                type: match ((string) ($row['method'] ?? 'btree')) {
                    'gin' => IndexType::Gin,
                    'gist' => IndexType::Gist,
                    'brin' => IndexType::Brin,
                    'hash' => IndexType::Hash,
                    default => IndexType::BTree,
                },
            );
        }

        return $indexes;
    }

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function foreignKeys(TableIdentifier $table): array
    {
        $sql = 'select tc.constraint_name, kcu.column_name, ccu.table_name as foreign_table, ccu.column_name as foreign_column '
            .'from information_schema.table_constraints tc '
            .'join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name and kcu.constraint_schema = tc.constraint_schema '
            .'join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.constraint_schema = tc.constraint_schema '
            ."where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = coalesce(?, current_schema()) and tc.table_name = ? "
            .'order by tc.constraint_name, kcu.ordinal_position';
        $rows = $this->executor->rows(new RawQuery($sql, [$table->schema, $table->name]));

        $grouped = [];
        foreach ($rows as $row) {
            $name = (string) $row['constraint_name'];
            $grouped[$name]['columns'][] = (string) $row['column_name'];
            $grouped[$name]['foreign_columns'][] = (string) $row['foreign_column'];
            $grouped[$name]['foreign_table'] = (string) $row['foreign_table'];
        }

        $keys = [];
        foreach ($grouped as $name => $data) {
            $keys[] = new ForeignKeyDefinition(
                name: $name,
                columns: $data['columns'],
                foreignTable: $data['foreign_table'],
                foreignColumns: $data['foreign_columns'],
            );
        }

        return $keys;
    }

    private static function kindFor(string $type): TypeKind
    {
        return match (true) {
            $type === 'int2' || $type === 'int4' || $type === 'smallint' || $type === 'integer' => TypeKind::Integer,
            $type === 'int8' || $type === 'bigint' => TypeKind::BigInteger,
            str_contains($type, 'numeric'), $type === 'decimal' => TypeKind::Decimal,
            $type === 'float4' || $type === 'float8' || $type === 'real' || $type === 'double precision' => TypeKind::Float,
            $type === 'bool' || $type === 'boolean' => TypeKind::Boolean,
            $type === 'date' => TypeKind::Date,
            $type === 'time' => TypeKind::Time,
            str_contains($type, 'timestamp') => TypeKind::Timestamp,
            $type === 'json' => TypeKind::Json,
            $type === 'jsonb' => TypeKind::Jsonb,
            $type === 'uuid' => TypeKind::Uuid,
            $type === 'bytea' => TypeKind::Blob,
            $type === 'text' => TypeKind::Text,
            str_ends_with($type, '[]') => TypeKind::Array,
            default => TypeKind::String,
        };
    }
}
