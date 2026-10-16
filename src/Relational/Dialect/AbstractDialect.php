<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect;

use OrmUniversal\Contracts\CompilerInterface;
use OrmUniversal\Contracts\DialectInterface;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\DatabaseFamily;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\ExpressionInterface;
use OrmUniversal\Contracts\MigrationCompilerInterface;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\SchemaOperationInterface;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\CompiledExpression;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Compiler\CompileContext;
use OrmUniversal\Relational\Compiler\SqlCompiler;
use OrmUniversal\Relational\Migration\Compiler;

/**
 * Base dialect: owns the capability set, identifier quoting, expression hooks and
 * delegates full query compilation to SqlCompiler. Every hook has a conservative
 * ANSI default; vendor dialects override exactly what their version documents.
 *
 * @api public
 */
abstract class AbstractDialect implements DialectInterface
{
    private ?CompilerInterface $compiler = null;

    private ?MigrationCompilerInterface $migrationCompiler = null;

    public function __construct(
        protected readonly DialectVersion $version,
        protected readonly CapabilitySet $capabilities,
    ) {}

    final public function version(): DialectVersion
    {
        return $this->version;
    }

    final public function capabilities(): CapabilitySet
    {
        return $this->capabilities;
    }

    final public function family(): DatabaseFamily
    {
        return DatabaseFamily::Relational;
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities->supports($capability);
    }

    public function requiredCapabilities(QueryObjectInterface $query): array
    {
        return $query->requiredCapabilities();
    }

    public function compiler(): CompilerInterface
    {
        return $this->compiler ??= new SqlCompiler($this);
    }

    public function compile(QueryObjectInterface $query): CompiledQuery
    {
        return $this->compiler()->compileQuery($query);
    }

    public function compileExpression(ExpressionInterface $expression): CompiledExpression
    {
        return $this->compiler()->compileExpression($expression);
    }

    public function compileExists(SelectQuery $query): CompiledQuery
    {
        $compiler = $this->compiler();

        if (! $compiler instanceof SqlCompiler) {
            throw new UnsupportedFeatureException(
                feature: 'EXISTS projection',
                engine: $this->vendor()->value,
                engineVersion: $this->version->raw,
                reason: 'The dialect compiler does not implement EXISTS projections.',
                alternatives: ['Use count() with a limit of one row'],
            );
        }

        return $compiler->compileExists($query);
    }

    public function existsProjection(string $subquery): string
    {
        return 'select exists('.$subquery.') as '.$this->quoteIdentifier('exists');
    }

    public function migrationCompiler(): MigrationCompilerInterface
    {
        return $this->migrationCompiler ??= new Compiler($this);
    }

    /**
     * @return list<string>
     */
    public function limitations(): array
    {
        return [];
    }

    /**
     * Migration lock/blocking warnings for a schema operation. Dialects override
     * with what they can actually detect; an empty list means "no known warning".
     *
     * @return list<string>
     */
    public function migrationWarnings(SchemaOperationInterface $operation): array
    {
        return [];
    }

    // ------------------------------------------------------------------
    // Identifier quoting
    // ------------------------------------------------------------------

    public function quoteChar(): string
    {
        return '"';
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        $quote = $this->quoteChar();
        $parts = explode('.', $identifier);
        $quoted = [];

        foreach ($parts as $part) {
            if ($part === '*') {
                $quoted[] = '*';

                continue;
            }

            $quoted[] = $quote.str_replace($quote, $quote.$quote, $part).$quote;
        }

        return implode('.', $quoted);
    }

    public function escapeStringLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    // ------------------------------------------------------------------
    // Statement level hooks
    // ------------------------------------------------------------------

    public function booleanLiteral(bool $value): string
    {
        return $value ? 'true' : 'false';
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

    public function updateDeleteLimit(?int $limit): string
    {
        throw new UnsupportedFeatureException(
            feature: 'UPDATE/DELETE LIMIT',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'The dialect does not support a row limit on UPDATE/DELETE without a subquery',
            alternatives: ['Restrict the affected rows with a WHERE clause on the primary key', 'Use a subquery with IN (SELECT ... LIMIT n)'],
        );
    }

    public function lockClause(?string $mode): ?string
    {
        if ($mode === null) {
            return null;
        }

        throw new UnsupportedFeatureException(
            feature: 'Row locking',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'Row level locking clause not supported for this dialect',
            alternatives: ['Use optimistic locking with a version column', 'Wrap the read and write in a transaction'],
        );
    }

    public function returningClause(?array $columns): ?string
    {
        return null;
    }

    public function outputClause(?array $columns, string $verb): string
    {
        return '';
    }

    public function supportsOutputClause(): bool
    {
        return $this->supports(Capability::OUTPUT_CLAUSE);
    }

    public function upsertStyle(): UpsertStyle
    {
        return UpsertStyle::OnConflict;
    }

    /**
     * @param  callable(string): string  $quoteColumn
     */
    public function upsertSuffix(InsertQuery $query, callable $quoteColumn): string
    {
        throw new UnsupportedFeatureException(
            feature: 'UPSERT',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'The dialect does not expose ON CONFLICT / ON DUPLICATE KEY upserts',
            alternatives: ['Use a transaction with SELECT then INSERT/UPDATE', 'Use merge() when supported'],
        );
    }

    // ------------------------------------------------------------------
    // Expression hooks
    // ------------------------------------------------------------------

    public function functionName(string $name): string
    {
        return strtoupper($name);
    }

    public function compileIlike(string $left, string $right): string
    {
        return 'LOWER('.$left.') LIKE LOWER('.$right.')';
    }

    public function compileRegex(string $left, string $right): string
    {
        throw new UnsupportedFeatureException(
            feature: 'REGEXP',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'Regular expression operator not available',
            alternatives: ['Use LIKE for simple patterns', 'Filter in the application layer'],
        );
    }

    public function compileIsDistinctFrom(string $left, string $right, bool $negated): string
    {
        $sql = '(('.$left.' = '.$right.') OR ('.$left.' IS NULL AND '.$right.' IS NULL))';

        return $negated ? $sql : 'NOT '.$sql;
    }

    /**
     * Whether the dialect supports the `BINARY` comparison operator.
     */
    public function supportsBinaryComparison(): bool
    {
        return false;
    }

    /**
     * Whether the dialect supports MySQL style index hints (USE/FORCE/IGNORE INDEX).
     */
    public function supportsIndexHint(): bool
    {
        return false;
    }

    /**
     * JSON length expression: json_length / jsonb_array_length / json_array_length.
     */
    public function compileJsonLength(string $sql, string $path): string
    {
        throw new UnsupportedFeatureException(
            feature: 'JSON_LENGTH',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'JSON length predicate is not supported for this dialect/version',
            alternatives: ['Compare a specific JSON path with JSON access/equality', 'Normalize the data and use a relational predicate'],
        );
    }

    /**
     * JSON overlaps predicate: json_overlaps (MySQL/MariaDB).
     */
    public function compileJsonOverlaps(string $sql, string $placeholder): string
    {
        throw new UnsupportedFeatureException(
            feature: 'JSON_OVERLAPS',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'JSON overlap predicate is not supported for this dialect/version',
            alternatives: ['Expand the array in the application layer', 'Use JSON_CONTAINS per element'],
        );
    }

    /**
     * Full-text predicate. `$placeholder` already carries the search value.
     *
     * @param  list<string>  $columns
     * @param  array{mode?: string|null, expanded?: bool, language?: string|null, vector?: bool}  $options
     */
    public function compileFullText(array $columns, string $placeholder, array $options): string
    {
        throw new UnsupportedFeatureException(
            feature: 'FULLTEXT',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'Full-text search is not supported for this dialect/version',
            alternatives: ['Create a fulltext index on the supported engine', 'Use LIKE for small datasets'],
        );
    }

    /**
     * Dialect keyword for LATERAL joins, or null when unsupported.
     */
    public function lateralJoinKeyword(JoinType $type): ?string
    {
        return null;
    }

    /**
     * Whether LATERAL joins need an explicit ON clause (false for APPLY engines).
     */
    public function lateralJoinNeedsOn(): bool
    {
        return true;
    }

    /**
     * Whether UPDATE ... FROM is available (PostgreSQL).
     */
    public function supportsUpdateFrom(): bool
    {
        return false;
    }

    /**
     * Tables placed between UPDATE <target> and SET (MySQL/MariaDB comma style).
     *
     * @param  list<string>  $tablesSql
     */
    public function updateFromBeforeSet(array $tablesSql, string $targetSql): string
    {
        return '';
    }

    /**
     * FROM clause appended after SET/OUTPUT (PostgreSQL/SQLite/SQL Server style).
     * The default throws: dialects that emulate UPDATE FROM override this.
     *
     * @param  list<string>  $tablesSql
     */
    public function updateFromClause(array $tablesSql, string $targetSql): string
    {
        throw new UnsupportedFeatureException(
            feature: 'UPDATE FROM',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'The dialect has no UPDATE ... FROM equivalent',
            alternatives: ['Use a subquery inside the SET expression', 'Split the update into two statements'],
        );
    }

    /**
     * Whether STRAIGHT_JOIN is native (MySQL/MariaDB). Elsewhere it degrades to
     * a regular join with a compilation warning: data is identical.
     */
    public function supportsStraightJoin(): bool
    {
        return false;
    }

    public function supportsStatementTimeout(): bool
    {
        return false;
    }

    public function statementTimeoutStatement(int $seconds): ?string
    {
        return null;
    }

    public function statementTimeoutResetStatement(): ?string
    {
        return null;
    }

    public function supportsVectorDistance(): bool
    {
        return false;
    }

    /**
     * Compile the vector distance expression for the given column; the vector
     * value is passed as a bound placeholder.
     */
    public function compileVectorDistance(string $columnSql, string $vectorPlaceholder): string
    {
        throw new UnsupportedFeatureException(
            feature: 'VECTOR DISTANCE',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'Vector distance requires MariaDB 11.7+ or PostgreSQL with pgvector',
            alternatives: ['Filter in the application layer', 'Use dedicated search infrastructure (e.g. vector database)'],
        );
    }

    /**
     * Compile an INSERT IGNORE style statement. `$target` already contains the
     * quoted table, column list and output clause; `$source` is a VALUES list or
     * a SELECT statement.
     */
    public function insertIgnore(string $documented, string $target, string $source): string
    {
        throw new UnsupportedFeatureException(
            feature: 'INSERT IGNORE',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'The dialect has no native INSERT IGNORE syntax',
            alternatives: ['Use upsert() with the conflict key', 'Check existence inside a transaction before inserting'],
        );
    }

    /**
     * TRUNCATE statements for the dialect. SQLite emulates it with DELETE like
     * Laravel does; other engines use native TRUNCATE TABLE.
     *
     * @return list<array{sql: string, bindings: list<mixed>}>
     */
    public function truncateStatements(string $table, string $quotedTable): array
    {
        return [['sql' => 'truncate table '.$quotedTable, 'bindings' => []]];
    }

    /**
     * Random ordering function for inRandomOrder().
     */
    public function randomFunction(): string
    {
        return 'random()';
    }

    public function compileDatePart(string $part, string $sql): string
    {
        return match (strtolower($part)) {
            'date' => 'CAST('.$sql.' AS DATE)',
            'time' => 'CAST('.$sql.' AS TIME)',
            default => 'EXTRACT('.strtoupper($part).' FROM '.$sql.')',
        };
    }

    public function compileJson(string $sql, string $path, bool $asText): string
    {
        throw new UnsupportedFeatureException(
            feature: 'JSON access',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'JSON path access is not supported for this dialect/version',
            alternatives: ['Store the value in a normalized column', 'Extract the value in the application layer'],
        );
    }

    public function compileJsonContains(string $sql, string $path, string $placeholder): string
    {
        throw new UnsupportedFeatureException(
            feature: 'JSON_CONTAINS',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'JSON containment predicate is not supported for this dialect/version',
            alternatives: ['Compare a specific JSON path with JSON access/equality', 'Normalize the data and use a relational predicate'],
        );
    }

    public function compileJsonContainsKey(string $sql, string $path): string
    {
        throw new UnsupportedFeatureException(
            feature: 'JSON_CONTAINS_KEY',
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'JSON key existence predicate is not supported for this dialect/version',
            alternatives: ['Compare a specific JSON path IS NOT NULL'],
        );
    }

    /**
     * Compile a logical aggregate, mapping to the vendor function available for
     * this version. Bindings are added through the shared context.
     *
     * @param  callable(Expression): string  $compileArgument
     */
    public function compileAggregate(Aggregate $aggregate, callable $compileArgument, CompileContext $context): string
    {
        $argument = $this->aggregateArgument($aggregate, $compileArgument);
        $distinct = $aggregate->distinct ? 'distinct ' : '';

        return match ($aggregate->function) {
            AggregateFunction::Count => 'COUNT('.$distinct.$argument.')',
            AggregateFunction::CountDistinct => 'COUNT(DISTINCT '.$argument.')',
            AggregateFunction::Sum => 'SUM('.$distinct.$argument.')',
            AggregateFunction::Avg => 'AVG('.$distinct.$argument.')',
            AggregateFunction::Min => 'MIN('.$argument.')',
            AggregateFunction::Max => 'MAX('.$argument.')',
            AggregateFunction::StdDev => 'STDDEV('.$argument.')',
            AggregateFunction::Variance => 'VARIANCE('.$argument.')',
            AggregateFunction::GroupConcat => $this->compileGroupConcat($argument, $aggregate->separator, $aggregate->distinct),
            AggregateFunction::StringAgg => $this->compileStringAgg($argument, $aggregate->separator ?? ',', $aggregate->distinct),
            AggregateFunction::AnyValue => $this->compileAnyValue($argument),
            AggregateFunction::Median => $this->compileMedian($argument),
            AggregateFunction::Percentile => throw $this->unsupported('PERCENTILE'),
        };
    }

    protected function aggregateArgument(Aggregate $aggregate, callable $compileArgument): string
    {
        if (is_string($aggregate->expression)) {
            return $aggregate->expression;
        }

        return $compileArgument($aggregate->expression);
    }

    protected function compileGroupConcat(string $argument, ?string $separator, bool $distinct): string
    {
        throw $this->unsupported('GROUP_CONCAT');
    }

    protected function compileStringAgg(string $argument, string $separator, bool $distinct): string
    {
        throw $this->unsupported('STRING_AGG');
    }

    protected function compileAnyValue(string $argument): string
    {
        throw $this->unsupported('ANY_VALUE');
    }

    protected function compileMedian(string $argument): string
    {
        throw $this->unsupported('MEDIAN');
    }

    protected function unsupported(string $feature): UnsupportedFeatureException
    {
        return new UnsupportedFeatureException(
            feature: $feature,
            engine: $this->vendor()->value,
            engineVersion: $this->version->raw,
            reason: 'Aggregate function not supported for this dialect/version',
            alternatives: ['Aggregate in the application layer', 'Use a portable aggregate such as COUNT/SUM/MIN/MAX/AVG'],
        );
    }
}
