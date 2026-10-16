<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Dialect\Capabilities\PostgreSqlCapabilities;

/**
 * PostgreSQL dialect for supported majors 14-18.
 *
 * Notes:
 * - ON CONFLICT (cols) DO UPDATE with EXCLUDED is the upsert path.
 * - RETURNING is available for INSERT/UPDATE/DELETE and DELETE ... RETURNING.
 * - Advanced types: JSONB, arrays, enums, UUID, ranges, time zones.
 * - SPATIAL and vector search require optional extensions (PostGIS, pgvector) and
 *   are reported unsupported unless a plugin registers them.
 *
 * @api public
 */
final class PostgreSqlDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'pgsql-'.$this->version->numeric();
    }

    public function vendor(): DialectVendor
    {
        return DialectVendor::Postgresql;
    }

    public function driverName(): string
    {
        return 'pgsql';
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
            return ' limit all offset '.max(0, $offset);
        }

        return ' limit '.max(0, (int) $limit).' offset '.max(0, $offset);
    }

    public static function make(?string $version = null): self
    {
        $version ??= '18.0';

        return new self(
            DialectVersion::parse(DialectVendor::Postgresql, $version),
            PostgreSqlCapabilities::for(DialectVersion::parse(DialectVendor::Postgresql, $version)),
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

    public function returningClause(?array $columns): string
    {
        return 'returning '.$this->columnList($columns);
    }

    public function lockClause(?string $mode): ?string
    {
        if ($mode === null) {
            return null;
        }

        $mode = strtolower(trim($mode));
        $sql = 'for update';

        if (str_contains($mode, 'skip locked')) {
            $sql .= ' skip locked';
        } elseif (str_contains($mode, 'nowait')) {
            $sql .= ' nowait';
        }

        return $sql;
    }

    public function compileIlike(string $left, string $right): string
    {
        return $left.' ilike '.$right;
    }

    public function compileRegex(string $left, string $right): string
    {
        return $left.' ~ '.$right;
    }

    public function compileIsDistinctFrom(string $left, string $right, bool $negated): string
    {
        return $left.($negated ? ' is not distinct from ' : ' is distinct from ').$right;
    }

    public function compileDatePart(string $part, string $sql): string
    {
        return match (strtolower($part)) {
            'date' => '('.$sql.')::date',
            'time' => '('.$sql.')::time',
            default => parent::compileDatePart($part, $sql),
        };
    }

    public function insertIgnore(string $documented, string $target, string $source): string
    {
        return 'insert into '.$target.' '.$source.' on conflict do nothing';
    }

    /**
     * @param  list<string>  $columns
     * @param  array{mode?: string|null, expanded?: bool, language?: string|null, vector?: bool}  $options
     */
    public function compileFullText(array $columns, string $placeholder, array $options): string
    {
        $language = (string) ($options['language'] ?? 'english');
        if (preg_match('/^[a-z_]+$/', $language) !== 1) {
            $language = 'english';
        }

        $vector = (bool) ($options['vector'] ?? false);

        $vectorColumns = [];
        foreach ($columns as $column) {
            $quoted = $this->quoteIdentifier($column);
            $vectorColumns[] = $vector ? $quoted : "to_tsvector('".$language."', ".$quoted.')';
        }

        $mode = match ((string) ($options['mode'] ?? '')) {
            'phrase' => 'phraseto_tsquery',
            'websearch' => 'websearch_to_tsquery',
            'raw' => 'to_tsquery',
            default => 'plainto_tsquery',
        };

        return '('.implode(' || ', $vectorColumns).') @@ '.$mode."('".$language."', ".$placeholder.')';
    }

    public function lateralJoinKeyword(JoinType $type): string
    {
        return $type === JoinType::LeftLateral ? ' left join lateral ' : ' join lateral ';
    }

    public function supportsUpdateFrom(): bool
    {
        return true;
    }

    /**
     * @param  list<string>  $tablesSql
     */
    public function updateFromClause(array $tablesSql, string $targetSql): string
    {
        return $tablesSql === [] ? '' : ' from '.implode(', ', $tablesSql);
    }

    public function supportsStatementTimeout(): bool
    {
        return true;
    }

    public function statementTimeoutStatement(int $seconds): string
    {
        return 'set statement_timeout = '.max(1, $seconds * 1000);
    }

    public function statementTimeoutResetStatement(): string
    {
        return 'set statement_timeout = 0';
    }

    public function supportsVectorDistance(): bool
    {
        return true;
    }

    public function compileVectorDistance(string $columnSql, string $vectorPlaceholder): string
    {
        return '('.$columnSql.' <=> '.$vectorPlaceholder.')';
    }

    public function compileJsonOverlaps(string $sql, string $placeholder): string
    {
        return 'exists (select 1 from jsonb_array_elements(('.$sql.')::jsonb) as elem'
            .' where elem in (select e from jsonb_array_elements(('.$placeholder.')::jsonb) as e))';
    }

    public function compileStringAgg(string $argument, string $separator, bool $distinct): string
    {
        return 'string_agg('.($distinct ? 'distinct ' : '').$argument.', '.$this->escapeStringLiteral($separator).')';
    }

    public function compileJson(string $sql, string $path, bool $asText): string
    {
        $segments = $this->jsonPathSegments($path);

        return $asText ? '('.$sql." #>> '{".$segments."}')" : '('.$sql." #> '{".$segments."}')";
    }

    public function compileJsonContains(string $sql, string $path, string $placeholder): string
    {
        $normalized = trim($path);

        if ($normalized === '' || $normalized === '$') {
            return $sql.' @> '.$placeholder.'::jsonb';
        }

        return $sql." #> '{".$this->jsonPathSegments($normalized)."}' @> ".$placeholder.'::jsonb';
    }

    public function compileJsonContainsKey(string $sql, string $path): string
    {
        return $sql." #> '{".$this->jsonPathSegments($path)."}' is not null";
    }

    public function compileJsonLength(string $sql, string $path): string
    {
        return 'jsonb_array_length(('.$sql." #> '{".$this->jsonPathSegments($path)."}')::jsonb)";
    }

    protected function jsonPathSegments(string $path): string
    {
        $path = trim($path);
        $path = ltrim($path, '$');
        $path = ltrim($path, '.');

        if ($path === '') {
            return '';
        }

        $parts = explode('.', $path);

        return implode(',', array_map(static fn (string $part): string => trim($part, '"\''), $parts));
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
            'SPATIAL requires the PostGIS extension',
            'Vector search requires the pgvector extension',
            'No UPDATE/DELETE LIMIT: use a primary key subquery',
            'Index names are limited to 63 characters',
        ];
    }

    public function migrationWarnings(SchemaOperationInterface $operation): array
    {
        return match ($operation->operationName()) {
            'add_index' => ['CREATE INDEX takes a SHARE lock; consider CREATE INDEX CONCURRENTLY in production.'],
            'drop_index' => ['DROP INDEX takes an ACCESS EXCLUSIVE lock on the table.'],
            'add_columns', 'drop_columns', 'add_foreign_key', 'drop_foreign_key' => ['ALTER TABLE takes an ACCESS EXCLUSIVE lock and blocks reads and writes.'],
            default => [],
        };
    }
}
