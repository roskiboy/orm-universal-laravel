<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Compiler\CompileContext;
use OrmUniversal\Relational\Dialect\Capabilities\MariaDbCapabilities;

/**
 * MariaDB dialect (10.6 legacy, 10.11 LTS, 11.4 LTS, 11.8 LTS, 12.x, 13.x).
 *
 * Notes:
 * - INSERT ... RETURNING requires MariaDB 10.5+ and is not allowed with
 *   INSERT ... SELECT; UPDATE/DELETE RETURNING 10.0+.
 * - MariaDB Vector (VECTOR type) requires 11.7+.
 *
 * @api public
 */
class MariaDbDialect extends MySqlDialect
{
    public function name(): string
    {
        return 'mariadb-'.$this->version->numeric();
    }

    public function vendor(): DialectVendor
    {
        return DialectVendor::Mariadb;
    }

    public static function make(?string $version = null): self
    {
        $version ??= '11.6.2';

        return new self(
            DialectVersion::parse(DialectVendor::Mariadb, $version),
            MariaDbCapabilities::for(DialectVersion::parse(DialectVendor::Mariadb, $version)),
        );
    }

    public function returningClause(?array $columns): ?string
    {
        if (! $this->supports(Capability::RETURNING)) {
            return null;
        }

        return 'returning '.$this->columnList($columns);
    }

    public function compileMedian(string $argument): string
    {
        return $this->supports(Capability::PERCENTILE)
            ? 'median('.$argument.')'
            : parent::compileMedian($argument);
    }

    public function compileJsonOverlaps(string $sql, string $placeholder): string
    {
        if (! $this->version->atLeast(10, 9)) {
            throw new UnsupportedFeatureException(
                feature: 'JSON_OVERLAPS',
                engine: 'mariadb',
                engineVersion: $this->version->raw,
                reason: 'JSON_OVERLAPS requires MariaDB 10.9+',
                alternatives: ['Use JSON_CONTAINS per element', 'Expand the array in the application layer'],
            );
        }

        return parent::compileJsonOverlaps($sql, $placeholder);
    }

    public function lateralJoinKeyword(JoinType $type): string
    {
        throw new UnsupportedFeatureException(
            feature: 'LATERAL JOIN',
            engine: 'mariadb',
            engineVersion: $this->version->raw,
            reason: 'MariaDB does not implement LATERAL derived tables',
            alternatives: ['Rewrite with joinSub()', 'Use a correlated EXISTS subquery', 'Upgrade to MySQL 8.0.14+ for LATERAL'],
        );
    }

    public function statementTimeoutStatement(int $seconds): ?string
    {
        return 'set session max_statement_time = '.max(0.001, (float) $seconds);
    }

    public function statementTimeoutResetStatement(): ?string
    {
        return 'set session max_statement_time = 0';
    }

    public function supportsVectorDistance(): bool
    {
        return $this->version->atLeast(11, 7);
    }

    public function compileVectorDistance(string $columnSql, string $vectorPlaceholder): string
    {
        if (! $this->version->atLeast(11, 7)) {
            throw new UnsupportedFeatureException(
                feature: 'VECTOR DISTANCE',
                engine: 'mariadb',
                engineVersion: $this->version->raw,
                reason: 'MariaDB Vector functions require MariaDB 11.7+',
                alternatives: ['Upgrade MariaDB to 11.7+', 'Filter in the application layer'],
            );
        }

        return 'vec_distance_cosine('.$columnSql.', vec_fromtext('.$vectorPlaceholder.'))';
    }

    public function compileAggregate(Aggregate $aggregate, callable $compileArgument, CompileContext $context): string
    {
        if ($aggregate->function === AggregateFunction::Median) {
            return $this->compileMedian($this->aggregateArgument($aggregate, $compileArgument));
        }

        return parent::compileAggregate($aggregate, $compileArgument, $context);
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
            'INSERT ... RETURNING is not supported together with INSERT ... SELECT before MariaDB 10.5',
            'VALUES() inside ON DUPLICATE KEY UPDATE remains the supported spelling',
            'JSON is an alias of LONGTEXT: no native binary JSON type (jsonb)',
            'FUNCTIONAL indexes are expressed through generated columns',
        ];
    }
}
