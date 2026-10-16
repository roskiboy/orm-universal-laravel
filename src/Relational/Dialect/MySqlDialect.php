<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Dialect\Capabilities\MySqlCapabilities;

/**
 * MySQL dialect (8.0 LTS legacy, 8.4 LTS, 9.x LTS, 26.x Innovation).
 *
 * Notes:
 * - No RETURNING clause; the follow-up SELECT / lastInsertId path is the
 *   documented alternative.
 * - ON DUPLICATE KEY UPDATE col = VALUES(col) is used; MySQL 8.0.20+ reports a
 *   deprecation warning for VALUES() but keeps it functional.
 * - `LIMIT` and `ORDER BY` are supported on UPDATE/DELETE.
 *
 * @api public
 */
class MySqlDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'mysql-'.$this->version->numeric();
    }

    public function vendor(): DialectVendor
    {
        return DialectVendor::Mysql;
    }

    public function driverName(): string
    {
        return 'mysql';
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
            return ' limit 18446744073709551615 offset '.max(0, $offset);
        }

        return ' limit '.max(0, (int) $limit).' offset '.max(0, $offset);
    }

    public static function make(?string $version = null): self
    {
        return new self(
            DialectVersion::parse(DialectVendor::Mysql, $version ?? '8.4.0'),
            MySqlCapabilities::for(DialectVersion::parse(DialectVendor::Mysql, $version ?? '8.4.0')),
        );
    }

    public function quoteChar(): string
    {
        return '`';
    }

    public function upsertStyle(): UpsertStyle
    {
        return UpsertStyle::OnDuplicateKey;
    }

    public function upsertSuffix(InsertQuery $query, callable $quoteColumn): string
    {
        $columns = $query->updateColumns() ?? $query->columns();
        $keys = $query->conflictColumns() ?? [];
        $assignments = [];

        foreach ($columns as $column) {
            if (in_array($column, $keys, true)) {
                continue;
            }

            $quoted = $quoteColumn($column);
            $assignments[] = $quoted.' = VALUES('.$quoted.')';
        }

        if ($assignments === []) {
            $first = $quoteColumn($columns[0] ?? ($query->columns()[0] ?? 'id'));
            $assignments[] = $first.' = '.$first;
        }

        return 'on duplicate key update '.implode(', ', $assignments);
    }

    public function compileGroupConcat(string $argument, ?string $separator, bool $distinct): string
    {
        $sql = 'group_concat('.($distinct ? 'distinct ' : '').$argument;

        if ($separator !== null && $separator !== ',') {
            $sql .= ' separator '.$this->escapeStringLiteral($separator);
        }

        return $sql.')';
    }

    public function compileAnyValue(string $argument): string
    {
        return $this->supports(Capability::ANY_VALUE)
            ? 'any_value('.$argument.')'
            : parent::compileAnyValue($argument);
    }

    public function compileJson(string $sql, string $path, bool $asText): string
    {
        $path = $this->normalizeJsonPath($path);
        $extract = 'JSON_EXTRACT('.$sql.', '.$this->escapeStringLiteral($path).')';

        return $asText ? 'JSON_UNQUOTE('.$extract.')' : $extract;
    }

    public function compileJsonContains(string $sql, string $path, string $placeholder): string
    {
        $path = $this->normalizeJsonPath($path);

        return 'JSON_CONTAINS('.$sql.', '.$placeholder.', '.$this->escapeStringLiteral($path).')';
    }

    public function compileJsonContainsKey(string $sql, string $path): string
    {
        $path = $this->normalizeJsonPath($path);

        return 'JSON_CONTAINS_PATH('.$sql.", 'one', ".$this->escapeStringLiteral($path).')';
    }

    public function supportsIndexHint(): bool
    {
        return true;
    }

    public function compileJsonLength(string $sql, string $path): string
    {
        $path = $this->normalizeJsonPath($path);

        return 'JSON_LENGTH('.$sql.', '.$this->escapeStringLiteral($path).')';
    }

    public function compileJsonOverlaps(string $sql, string $placeholder): string
    {
        return 'JSON_OVERLAPS('.$sql.', '.$placeholder.')';
    }

    /**
     * @param  list<string>  $columns
     * @param  array{mode?: string|null, expanded?: bool, language?: string|null, vector?: bool}  $options
     */
    public function compileFullText(array $columns, string $placeholder, array $options): string
    {
        $columns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));

        $mode = ($options['mode'] ?? null) === 'boolean'
            ? ' in boolean mode'
            : ' in natural language mode';

        $expanded = ($options['expanded'] ?? false) && ($options['mode'] ?? null) !== 'boolean'
            ? ' with query expansion'
            : '';

        return 'match ('.$columns.') against ('.$placeholder.$mode.$expanded.')';
    }

    public function lateralJoinKeyword(JoinType $type): string
    {
        return $type === JoinType::LeftLateral ? ' left join lateral ' : ' join lateral ';
    }

    public function supportsStraightJoin(): bool
    {
        return true;
    }

    public function supportsUpdateFrom(): bool
    {
        return true;
    }

    /**
     * @param  list<string>  $tablesSql
     */
    public function updateFromBeforeSet(array $tablesSql, string $targetSql): string
    {
        return $tablesSql === [] ? '' : ', '.implode(', ', $tablesSql);
    }

    /**
     * @param  list<string>  $tablesSql
     */
    public function updateFromClause(array $tablesSql, string $targetSql): string
    {
        return '';
    }

    public function supportsStatementTimeout(): bool
    {
        return true;
    }

    public function statementTimeoutStatement(int $seconds): ?string
    {
        return 'set session max_execution_time = '.max(1, $seconds * 1000);
    }

    public function statementTimeoutResetStatement(): ?string
    {
        return 'set session max_execution_time = 0';
    }

    public function compileRegex(string $left, string $right): string
    {
        return $left.' regexp '.$right;
    }

    public function compileIsDistinctFrom(string $left, string $right, bool $negated): string
    {
        $sql = '('.$left.' <=> '.$right.')';

        return $negated ? $sql : 'NOT '.$sql;
    }

    public function supportsBinaryComparison(): bool
    {
        return true;
    }

    public function insertIgnore(string $documented, string $target, string $source): string
    {
        return 'insert ignore into '.$target.' '.$source;
    }

    public function randomFunction(): string
    {
        return 'rand()';
    }

    public function compileDatePart(string $part, string $sql): string
    {
        return match (strtolower($part)) {
            'date' => 'date('.$sql.')',
            'time' => 'time('.$sql.')',
            default => parent::compileDatePart($part, $sql),
        };
    }

    public function updateDeleteLimit(?int $limit): string
    {
        return ' limit '.max(0, (int) $limit);
    }

    public function lockClause(?string $mode): ?string
    {
        if ($mode === null) {
            return null;
        }

        $mode = strtolower(trim($mode));
        $sql = 'for update';

        if (str_contains($mode, 'skip locked')) {
            $sql .= $this->supports(Capability::SKIP_LOCKED) ? ' skip locked' : '';
        } elseif (str_contains($mode, 'nowait')) {
            $sql .= ' nowait';
        }

        return $sql;
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
            'No RETURNING clause: INSERT/UPDATE/DELETE cannot return rows directly',
            'VALUES() inside ON DUPLICATE KEY UPDATE is deprecated in MySQL 8.0.20+ (still functional)',
            'Table name and column name limits: 64 characters',
        ];
    }

    public function migrationWarnings(SchemaOperationInterface $operation): array
    {
        return match ($operation->operationName()) {
            'add_columns', 'drop_columns', 'add_index', 'drop_index', 'add_foreign_key', 'drop_foreign_key', 'rename_table' => [
                'ALTER TABLE may rebuild the table and hold metadata locks; review online DDL before running in production.',
            ],
            default => [],
        };
    }
}
