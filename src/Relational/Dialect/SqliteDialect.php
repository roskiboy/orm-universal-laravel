<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Dialect\Capabilities\SqliteCapabilities;

/**
 * SQLite dialect, baseline 3.35 (RETURNING) up to current 3.54.
 *
 * Notes:
 * - ON CONFLICT DO UPDATE upserts; RETURNING available since 3.35.
 * - JSON functions are built into the core since 3.38 (-> and ->> operators).
 * - Single writer: no row level locking, no LATERAL; writes are serialized.
 *
 * @api public
 */
final class SqliteDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'sqlite-'.$this->version->numeric();
    }

    public function vendor(): DialectVendor
    {
        return DialectVendor::Sqlite;
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    public function limitOffset(?int $limit, ?int $offset, bool $hasOrderBy): string
    {
        if ($limit === null && $offset === null) {
            return '';
        }

        if ($offset === null) {
            return ' limit '.max(0, (int) $limit);
        }

        if ($limit === null) {
            return ' limit -1 offset '.max(0, $offset);
        }

        return ' limit '.max(0, (int) $limit).' offset '.max(0, $offset);
    }

    public static function make(?string $version = null): self
    {
        $version ??= '3.49.2';

        return new self(
            DialectVersion::parse(DialectVendor::Sqlite, $version),
            SqliteCapabilities::for(DialectVersion::parse(DialectVendor::Sqlite, $version)),
        );
    }

    public function upsertStyle(): UpsertStyle
    {
        return UpsertStyle::OnConflict;
    }

    public function upsertSuffix(InsertQuery $query, callable $quoteColumn): string
    {
        $keys = $query->conflictColumns() ?? [];
        $columns = $query->updateColumns() ?? $query->columns();
        $assignments = [];

        foreach ($columns as $column) {
            $quoted = $quoteColumn($column);
            $assignments[] = $quoted.' = excluded.'.$quoted;
        }

        $target = $keys === []
            ? ''
            : ' ('.implode(', ', array_map(fn (string $key): string => $quoteColumn($key), $keys)).')';

        return 'on conflict'.$target.' do update set '.implode(', ', $assignments);
    }

    public function returningClause(?array $columns): ?string
    {
        if (! $this->supports(Capability::RETURNING)) {
            return null;
        }

        return 'returning '.$this->columnList($columns);
    }

    public function insertIgnore(string $documented, string $target, string $source): string
    {
        return 'insert or ignore into '.$target.' '.$source;
    }

    public function supportsUpdateFrom(): bool
    {
        return $this->version->atLeast(3, 33);
    }

    /**
     * @param  list<string>  $tablesSql
     */
    public function updateFromClause(array $tablesSql, string $targetSql): string
    {
        if (! $this->version->atLeast(3, 33)) {
            throw new UnsupportedFeatureException(
                feature: 'UPDATE FROM',
                engine: 'sqlite',
                engineVersion: $this->version->raw,
                reason: 'SQLite supports UPDATE ... FROM since 3.33',
                alternatives: ['Upgrade SQLite', 'Use a correlated subquery inside the SET expression'],
            );
        }

        return $tablesSql === [] ? '' : ' from '.implode(', ', $tablesSql);
    }

    public function compileJsonOverlaps(string $sql, string $placeholder): string
    {
        return 'exists (select 1 from json_each('.$sql.') where json_each.value in'
            .' (select value from json_each('.$placeholder.')))';
    }

    public function truncateStatements(string $table, string $quotedTable): array
    {
        return [
            ['sql' => 'delete from sqlite_sequence where name = ?', 'bindings' => [$table]],
            ['sql' => 'delete from '.$quotedTable, 'bindings' => []],
        ];
    }

    public function compileDatePart(string $part, string $sql): string
    {
        $normalized = strtolower($part);

        if ($normalized === 'date') {
            return "strftime('%Y-%m-%d', ".$sql.')';
        }        if ($normalized === 'time') {
            return "strftime('%H:%M:%S', ".$sql.')';
        }

        $format = match ($normalized) {
            'year' => '%Y',
            'month' => '%m',
            'day' => '%d',
            'hour' => '%H',
            'minute' => '%M',
            'second' => '%S',
            'dow', 'weekday' => '%w',
            'doy' => '%j',
            default => throw new UnsupportedFeatureException(
                feature: 'DATE_PART '.$part,
                engine: 'sqlite',
                engineVersion: $this->version->raw,
                reason: 'SQLite strftime does not provide that date part',
                alternatives: ['Extract the part in the application layer'],
            ),
        };

        return "CAST(strftime('".$format."', ".$sql.') AS INTEGER)';
    }

    public function compileJson(string $sql, string $path, bool $asText): string
    {
        return 'json_extract('.$sql.', '.$this->escapeStringLiteral($this->normalizeJsonPath($path)).')';
    }

    public function compileJsonContains(string $sql, string $path, string $placeholder): string
    {
        $path = $this->normalizeJsonPath($path);
        $literal = $this->escapeStringLiteral($path);
        $target = $sql;

        // Subset semantics with SQLite JSON1: object candidates require every
        // scalar member to match; array candidates require every element to be
        // present; scalar candidates are compared against the array members.
        // Nested object members are compared as JSON text values.
        return '('
            .'select case json_type(c)'
            ." when 'object' then not exists (select 1 from json_each(c) as j where coalesce(json_extract({$target}, {$literal} || '.' || j.key), '#ude-null#') <> coalesce(j.value, '#ude-null#'))"
            ." when 'array' then not exists (select 1 from json_each(c) as j where not exists (select 1 from json_each({$target}, {$literal}) as t where t.value = j.value))"
            ." else exists (select 1 from json_each({$target}, {$literal}) as t where t.value = json(c))"
            .' end from (select json('.$placeholder.') as c)'
            .')';
    }

    public function compileJsonContainsKey(string $sql, string $path): string
    {
        $path = $this->normalizeJsonPath($path);

        return 'json_type('.$sql.', '.$this->escapeStringLiteral($path).') is not null';
    }

    public function compileJsonLength(string $sql, string $path): string
    {
        $path = $this->normalizeJsonPath($path);

        return 'json_array_length('.$sql.', '.$this->escapeStringLiteral($path).')';
    }

    public function compileGroupConcat(string $argument, ?string $separator, bool $distinct): string
    {
        if ($distinct && $separator !== null && $separator !== ',') {
            throw new UnsupportedFeatureException(
                feature: 'GROUP_CONCAT DISTINCT with custom separator',
                engine: 'sqlite',
                engineVersion: $this->version->raw,
                reason: 'group_concat(DISTINCT x, separator) only accepts one argument in SQLite',
                alternatives: ['Use the default separator', 'Pre-aggregate DISTINCT values in a subquery'],
            );
        }

        if ($separator === null || $separator === ',') {
            return 'group_concat('.($distinct ? 'distinct ' : '').$argument.')';
        }

        return 'group_concat('.$argument.', '.$this->escapeStringLiteral($separator).')';
    }

    public function compileIsDistinctFrom(string $left, string $right, bool $negated): string
    {
        return $left.($negated ? ' is ' : ' is not ').$right;
    }

    public function compileAnyValue(string $argument): string
    {
        // SQLite has no any_value(); MIN(x) returns the value in single groups.
        throw new UnsupportedFeatureException(
            feature: 'ANY_VALUE',
            engine: 'sqlite',
            engineVersion: $this->version->raw,
            reason: 'SQLite does not implement any_value()',
            alternatives: ['Use MIN() or MAX() when the group has a single value', 'Aggregate in the application layer'],
        );
    }

    protected function normalizeJsonPath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $path === '$') {
            return '$';
        }

        return str_starts_with($path, '$') ? $path : '$.'.$path;
    }

    /**
     * @param  list<string>|null  $columns
     */
    protected function columnList(?array $columns): string
    {
        if ($columns === null || $columns === ['*']) {
            return '*';
        }

        return implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
    }

    /**
     * @return list<string>
     */
    public function limitations(): array
    {
        return [
            'Single writer: concurrent writes are serialized, no row level locking',
            'No LATERAL joins, no UPDATE/DELETE LIMIT by default (compile option dependent)',
            'ALTER TABLE support is limited; complex migrations recreate the table',
            'Variable limit depends on SQLITE_MAX_VARIABLE_NUMBER (32766 on modern builds)',
        ];
    }

    public function migrationWarnings(SchemaOperationInterface $operation): array
    {
        return match ($operation->operationName()) {
            'add_columns', 'drop_columns' => ['SQLite may recreate the table for this operation; large tables need exclusive access and free disk space.'],
            default => [],
        };
    }
}
