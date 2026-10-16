<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Dialect\Capabilities\OracleCapabilities;

/**
 * Oracle dialect for 19c and 23ai/26ai.
 *
 * Notes:
 * - OFFSET/FETCH is native since 12c.
 * - MERGE is the upsert path.
 * - LISTAGG replaces STRING_AGG/GROUP_CONCAT; DISTINCT is not supported.
 * - The portable RETURNING path is not exposed because OUT parameter binding is
 *   not implemented by the driver layer; the platform reports the alternative.
 * - DDL statements are auto-committed; migrations cannot be rolled back.
 *
 * @api public
 */
final class OracleDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'oracle-'.$this->version->numeric();
    }

    public function vendor(): DialectVendor
    {
        return DialectVendor::Oracle;
    }

    public function driverName(): string
    {
        return 'oci';
    }

    public static function make(?string $version = null): self
    {
        $version ??= '19.0';

        return new self(
            DialectVersion::parse(DialectVendor::Oracle, $version),
            OracleCapabilities::for(DialectVersion::parse(DialectVendor::Oracle, $version)),
        );
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

        if ($offset === null) {
            return ' fetch first '.$limit.' rows only';
        }

        $sql = ' offset '.$offset.' rows';

        if ($limit !== null) {
            $sql .= ' fetch next '.$limit.' rows only';
        }

        return $sql;
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

    public function upsertStyle(): UpsertStyle
    {
        return UpsertStyle::Merge;
    }

    public function compileStringAgg(string $argument, string $separator, bool $distinct): string
    {
        if ($distinct) {
            throw new UnsupportedFeatureException(
                feature: 'LISTAGG DISTINCT',
                engine: 'oracle',
                engineVersion: $this->version->raw,
                reason: 'LISTAGG does not support DISTINCT directly',
                alternatives: ['Pre-aggregate DISTINCT values in a subquery', 'Collapse duplicates in the application layer'],
            );
        }

        return 'listagg('.$argument.', '.$this->escapeStringLiteral($separator).')';
    }

    public function compileRegex(string $left, string $right): string
    {
        return 'regexp_like('.$left.', '.$right.')';
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
        throw new UnsupportedFeatureException(
            feature: 'JSON_CONTAINS',
            engine: 'oracle',
            engineVersion: $this->version->raw,
            reason: 'JSON containment is expressed through JSON_EXISTS with a filter expression',
            alternatives: ['Use json_exists() through a raw parameterized expression', 'Compare a JSON_VALUE path'],
        );
    }

    public function compileMedian(string $argument): string
    {
        return 'median('.$argument.')';
    }

    public function compileIsDistinctFrom(string $left, string $right, bool $negated): string
    {
        // Oracle has no IS [NOT] DISTINCT FROM; the null-safe emulation is exact.
        return parent::compileIsDistinctFrom($left, $right, $negated);
    }

    public function compileDatePart(string $part, string $sql): string
    {
        return match (strtolower($part)) {
            'date' => 'trunc('.$sql.')',
            'time' => 'to_char('.$sql.", 'HH24:MI:SS')",
            default => parent::compileDatePart($part, $sql),
        };
    }

    public function randomFunction(): string
    {
        return 'dbms_random.value';
    }

    public function lateralJoinKeyword(JoinType $type): string
    {
        return $type === JoinType::LeftLateral ? ' outer apply ' : ' cross apply ';
    }

    public function lateralJoinNeedsOn(): bool
    {
        return false;
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
            'Portable RETURNING is not exposed: OUT parameter binding is not implemented (use follow-up SELECT)',
            'DDL is auto-committed, migrations cannot be rolled back',
            'No boolean literals in SQL before 23ai: use 1/0',
            'LISTAGG does not support DISTINCT',
            'SQL identifiers are limited to 30 characters before 12.2 and 128 after',
        ];
    }

    public function migrationWarnings(SchemaOperationInterface $operation): array
    {
        return ['DDL auto-commits in Oracle: the operation cannot be rolled back and commits any open transaction.'];
    }
}
