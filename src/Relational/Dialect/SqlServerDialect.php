<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Dialect\Capabilities\SqlServerCapabilities;

/**
 * SQL Server dialect for 2019, 2022 and 2025.
 *
 * Notes:
 * - RETURNING is expressed through the proprietary OUTPUT clause.
 * - MERGE is the upsert path for every supported version.
 * - OFFSET/FETCH requires an ORDER BY; the compiler injects ORDER BY (SELECT NULL)
 *   when a pure offset is requested without ordering.
 * - STRING_AGG does not support DISTINCT; the platform reports the limitation.
 * - Row locking uses hints (UPDLOCK/READPAST), so lock() is reported unsupported.
 *
 * @api public
 */
final class SqlServerDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'sqlserver-'.$this->version->numeric();
    }

    public function vendor(): DialectVendor
    {
        return DialectVendor::SqlServer;
    }

    public function driverName(): string
    {
        return 'sqlsrv';
    }

    public static function make(?string $version = null): self
    {
        $version ??= '2022';

        return new self(
            DialectVersion::parse(DialectVendor::SqlServer, $version),
            SqlServerCapabilities::for(DialectVersion::parse(DialectVendor::SqlServer, $version)),
        );
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        $parts = explode('.', $identifier);
        $quoted = [];

        foreach ($parts as $part) {
            $quoted[] = $part === '*' ? '*' : '['.str_replace(']', ']]', $part).']';
        }

        return implode('.', $quoted);
    }

    public function booleanLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    public function existsProjection(string $subquery): string
    {
        return 'select case when exists('.$subquery.') then 1 else 0 end as '.$this->quoteIdentifier('exists');
    }

    public function limitOffset(?int $limit, ?int $offset, bool $hasOrderBy): string
    {
        $limit = $limit === null ? null : max(0, $limit);
        $offset = $offset === null ? null : max(0, $offset);

        if ($limit === null && $offset === null) {
            return '';
        }

        $sql = '';

        if (! $hasOrderBy) {
            $sql .= ' order by (select null)';
        }

        $sql .= ' offset '.($offset ?? 0).' rows';

        if ($limit !== null) {
            $sql .= ' fetch next '.$limit.' rows only';
        }

        return $sql;
    }

    public function lockClause(?string $mode): ?string
    {
        throw new UnsupportedFeatureException(
            feature: 'Row locking (FOR UPDATE)',
            engine: 'sqlserver',
            engineVersion: $this->version->raw,
            reason: 'SQL Server uses locking hints (UPDLOCK/READPAST) instead of FOR UPDATE',
            alternatives: ['Use withLockHint() raw SQL with UPDLOCK', 'Use optimistic concurrency with rowversion', 'Wrap the operation in a transaction'],
        );
    }

    public function outputClause(?array $columns, string $verb): string
    {
        if ($columns === null) {
            return '';
        }

        $prefix = $verb === 'insert' ? 'inserted' : ($verb === 'update' ? 'inserted' : 'deleted');

        if ($columns === ['*']) {
            return ' output '.$prefix.'.*';
        }

        $list = implode(', ', array_map(fn (string $column): string => $prefix.'.'.$this->quoteIdentifier($column), $columns));

        return ' output '.$list;
    }

    public function upsertStyle(): UpsertStyle
    {
        return UpsertStyle::Merge;
    }

    public function compileStringAgg(string $argument, string $separator, bool $distinct): string
    {
        if ($distinct) {
            throw new UnsupportedFeatureException(
                feature: 'STRING_AGG DISTINCT',
                engine: 'sqlserver',
                engineVersion: $this->version->raw,
                reason: 'STRING_AGG does not accept DISTINCT before SQL Server 2022 compatibility level 160',
                alternatives: ['Pre-aggregate DISTINCT values in a subquery', 'Collapse duplicates in the application layer'],
            );
        }

        return 'string_agg('.$argument.', '.$this->escapeStringLiteral($separator).')';
    }

    public function compileDatePart(string $part, string $sql): string
    {
        return match (strtolower($part)) {
            'date' => 'cast('.$sql.' as date)',
            'time' => 'cast('.$sql.' as time)',
            default => 'datepart('.strtolower($part).', '.$sql.')',
        };
    }

    public function randomFunction(): string
    {
        return 'newid()';
    }

    public function lateralJoinKeyword(JoinType $type): string
    {
        return $type === JoinType::LeftLateral ? ' outer apply ' : ' cross apply ';
    }

    public function supportsUpdateFrom(): bool
    {
        return true;
    }

    /**
     * SQL Server requires the target table in the FROM list.
     *
     * @param  list<string>  $tablesSql
     */
    public function updateFromClause(array $tablesSql, string $targetSql): string
    {
        return ' from '.implode(', ', [$targetSql, ...$tablesSql]);
    }

    public function compileJsonOverlaps(string $sql, string $placeholder): string
    {
        return 'exists (select 1 from openjson('.$sql.') where value in'
            .' (select value from openjson('.$placeholder.')))';
    }

    public function lateralJoinNeedsOn(): bool
    {
        return false;
    }

    public function compileJsonLength(string $sql, string $path): string
    {
        $path = $this->normalizeJsonPath($path);

        return '(select count(*) from openjson('.$sql.', '.$this->escapeStringLiteral($path).'))';
    }

    public function compileJson(string $sql, string $path, bool $asText): string
    {
        $path = $this->normalizeJsonPath($path);

        return $asText
            ? 'json_value('.$sql.', '.$this->escapeStringLiteral($path).')'
            : 'json_query('.$sql.', '.$this->escapeStringLiteral($path).')';
    }

    public function compileJsonContains(string $sql, string $path, string $placeholder): string
    {
        if (! $this->supports(Capability::JSON_TABLE) || $this->version->major() < 2025) {
            throw new UnsupportedFeatureException(
                feature: 'JSON_CONTAINS',
                engine: 'sqlserver',
                engineVersion: $this->version->raw,
                reason: 'JSON_CONTAINS is available starting with SQL Server 2025',
                alternatives: ['Use JSON_VALUE equality on a specific path', 'Use OPENJSON with EXISTS'],
            );
        }

        $path = $this->normalizeJsonPath($path);

        return 'json_contains('.$sql.', '.$placeholder.', '.$this->escapeStringLiteral($path).')';
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
     * @return list<string>
     */
    public function limitations(): array
    {
        return [
            'No FOR UPDATE syntax: use locking hints (UPDLOCK/READPAST) through raw SQL',
            'STRING_AGG DISTINCT is not available',
            'OFFSET/FETCH requires ORDER BY',
            'Deep pagination performance degrades without keyset pagination',
        ];
    }

    public function migrationWarnings(SchemaOperationInterface $operation): array
    {
        return match ($operation->operationName()) {
            'add_columns', 'drop_columns', 'add_index', 'drop_index', 'add_foreign_key', 'drop_foreign_key' => [
                'DDL may block the table; verify locking and online operations (Enterprise) before production.',
            ],
            default => [],
        };
    }
}
