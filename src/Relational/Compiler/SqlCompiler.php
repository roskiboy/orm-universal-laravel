<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Compiler;

use OrmUniversal\Contracts\CompilerInterface;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\Enums\RawType;
use OrmUniversal\Contracts\Enums\SortDirection;
use OrmUniversal\Contracts\Exceptions\CompilationException;
use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\ExpressionInterface;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledExpression;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\Ordering;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Ast\AliasedExpression;
use OrmUniversal\Relational\Ast\Between;
use OrmUniversal\Relational\Ast\CaseExpression;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\DateExpression;
use OrmUniversal\Relational\Ast\DeleteQuery;
use OrmUniversal\Relational\Ast\Exists;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\FullText;
use OrmUniversal\Relational\Ast\FunctionCall;
use OrmUniversal\Relational\Ast\InList;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\Join;
use OrmUniversal\Relational\Ast\JsonExpression;
use OrmUniversal\Relational\Ast\JsonLengthExpression;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\Parameter;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Ast\RowValues;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\Subquery;
use OrmUniversal\Relational\Ast\UpdateQuery;
use OrmUniversal\Relational\Ast\Value;
use OrmUniversal\Relational\Ast\VectorDistance;
use OrmUniversal\Relational\Ast\WindowExpression;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Dialect\UpsertStyle;

/**
 * Compiles the query AST into dialect SQL with ordered bindings. Values are only
 * ever emitted as bindings; identifiers are quoted; raw SQL must declare itself.
 *
 * @api public
 */
final class SqlCompiler implements CompilerInterface
{
    private readonly CapabilityNegotiator $negotiator;

    public function __construct(
        private readonly AbstractDialect $dialect,
        ?CapabilityNegotiator $negotiator = null,
    ) {
        $this->negotiator = $negotiator ?? new CapabilityNegotiator;
    }

    public function compileQuery(QueryObjectInterface $query): CompiledQuery
    {
        if ($query instanceof RawQuery) {
            return $query->compiled();
        }

        $ast = $query->toAst();
        $this->negotiator->negotiate($ast, $this->dialect);

        $context = new CompileContext($this->dialect);

        $sql = match (true) {
            $query instanceof SelectQuery => $this->select($query, $context),
            $query instanceof InsertQuery => $this->insert($query, $context),
            $query instanceof UpdateQuery => $this->update($query, $context),
            $query instanceof DeleteQuery => $this->delete($query, $context),
            default => throw new CompilationException('Unsupported query object ['.$query::class.'].'),
        };

        return new CompiledQuery(
            sql: $sql,
            bindings: $context->bindings,
            fingerprint: QueryFingerprint::fromSql($sql, $context->bindingCount()),
            requiredCapabilities: $query->requiredCapabilities(),
            dialect: $this->dialect->name(),
            dialectVersion: $this->dialect->version()->raw,
            warnings: $context->warnings,
        );
    }

    public function compileExpression(ExpressionInterface $expression): CompiledExpression
    {
        $context = new CompileContext($this->dialect);
        $sql = $this->expression($expression, $context);

        return new CompiledExpression($sql, $context->bindings);
    }

    public function compileExists(SelectQuery $query): CompiledQuery
    {
        $context = new CompileContext($this->dialect);
        $inner = clone $query;
        $inner->clearOrderings()->clearLimitOffset();

        $sql = $this->dialect->existsProjection($this->selectCore($inner, $context, false));

        return new CompiledQuery(
            sql: $sql,
            bindings: $context->bindings,
            fingerprint: $query->fingerprint(),
            requiredCapabilities: [],
            dialect: $this->dialect->name(),
            dialectVersion: $this->dialect->version()->raw,
        );
    }

    // ------------------------------------------------------------------
    // Statements
    // ------------------------------------------------------------------

    private function select(SelectQuery $query, CompileContext $context): string
    {
        $hasUnions = $query->unions() !== [];
        $sql = $this->withClause($query, $context).$this->selectCore($query, $context, $hasUnions);

        foreach ($query->unions() as $union) {
            if ($union['query']->orders() !== [] || $union['query']->limitValue() !== null || $union['query']->offsetValue() !== null) {
                throw new CompilationException('ORDER BY / LIMIT on a UNION member is not portable; apply them to the outer query.');
            }

            $sql .= $union['all'] ? ' union all ' : ' union ';
            $sql .= $this->selectCore($union['query'], $context, false);
        }

        if ($hasUnions) {
            $sql .= $this->orderByClause($query->orders(), $context);
            $sql .= $this->limitOffset($query, $context, $this->hasOrder($query));
        }

        return $sql;
    }

    private function selectCore(SelectQuery $query, CompileContext $context, bool $isUnionBase): string
    {
        $columns = $this->projections($query->columns(), $context);

        $sql = 'select '.($query->isDistinct() ? 'distinct ' : '').$columns;
        $sql .= ' from '.$this->fromSource($query, $context);

        foreach ($query->joins() as $join) {
            $sql .= $this->join($join, $context);
        }

        if (($where = $query->whereExpression()) !== null) {
            $sql .= ' where '.$this->expression($where, $context);
        }

        if ($query->groups() !== []) {
            $sql .= ' group by '.implode(', ', array_map(fn (Expression $group): string => $this->expression($group, $context), $query->groups()));
        }

        if (($having = $query->havingExpression()) !== null) {
            $sql .= ' having '.$this->expression($having, $context);
        }

        if (! $isUnionBase) {
            $sql .= $this->orderByClause($query->orders(), $context);
            $sql .= $this->limitOffset($query, $context, $this->hasOrder($query));

            if (($lock = $query->lockMode()) !== null) {
                $clause = $this->dialect->lockClause($lock);
                if ($clause !== null) {
                    $sql .= ' '.$clause;
                }
            }
        }

        return $sql;
    }

    private function withClause(SelectQuery $query, CompileContext $context): string
    {
        if ($query->ctes() === []) {
            return '';
        }

        $recursive = false;
        $parts = [];
        foreach ($query->ctes() as $cte) {
            $recursive = $recursive || $cte->recursive;
            $columns = $cte->columns !== []
                ? ' ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $cte->columns)).')'
                : '';
            $parts[] = $this->dialect->quoteIdentifier($cte->name).$columns.' as ('.$this->select($cte->query, $context->nested()).')';
        }

        return 'with '.($recursive ? 'recursive ' : '').implode(', ', $parts).' ';
    }

    private function insert(InsertQuery $query, CompileContext $context): string
    {
        if ($query->isUpsert() && $this->dialect->upsertStyle() === UpsertStyle::Merge) {
            return $this->merge($query, $context);
        }

        $documented = $query->isReplace() ? 'replace' : 'insert';
        $table = $this->dialect->quoteIdentifier($query->table()->qualifiedName());

        $columns = $query->columns() === []
            ? ''
            : ' ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $query->columns())).')';

        $output = $this->dialect->outputClause($query->returningColumns(), 'insert');

        if ($query->selectQuery() !== null) {
            $source = $this->select($query->selectQuery(), $context->nested());

            if ($query->isIgnore()) {
                return $this->dialect->insertIgnore($documented, $table.$columns.$output, $source).$this->returningSuffix($query);
            }

            $sql = $documented.' into '.$table.$columns.$output.' '.$source;

            return $sql.$this->upsertSuffix($query).$this->returningSuffix($query);
        }

        $rows = [];
        foreach ($query->rowsData() as $row) {
            $values = [];
            foreach ($row as $value) {
                $values[] = $value instanceof Expression
                    ? $this->expression($value, $context)
                    : $context->bind($value);
            }
            $rows[] = '('.implode(', ', $values).')';
        }

        if ($rows === []) {
            throw new CompilationException('INSERT requires at least one row of values.');
        }

        $source = 'values '.implode(', ', $rows);

        if ($query->isIgnore()) {
            return $this->dialect->insertIgnore($documented, $table.$columns.$output, $source).$this->returningSuffix($query);
        }

        $sql = $documented.' into '.$table.$columns.$output.' '.$source;

        return $sql.$this->upsertSuffix($query).$this->returningSuffix($query);
    }

    private function merge(InsertQuery $query, CompileContext $context): string
    {
        $keys = $query->conflictColumns();
        if ($keys === null || $keys === []) {
            throw new UnsupportedFeatureException(
                feature: 'UPSERT (MERGE)',
                engine: $this->dialect->vendor()->value,
                engineVersion: $this->dialect->version()->raw,
                reason: 'MERGE requires an explicit conflict target; call onConflict([...])',
                alternatives: ['Provide the key columns via onConflict()', 'Use a transaction with SELECT then INSERT/UPDATE'],
            );
        }

        $table = $this->dialect->quoteIdentifier($query->table()->qualifiedName());
        $columns = $query->columns();
        $updateColumns = $query->updateColumns() ?? array_values(array_diff($columns, $keys));

        // USING (VALUES (...), (...)) AS src (col, col)
        $rows = [];
        foreach ($query->rowsData() as $row) {
            $values = [];
            foreach ($row as $value) {
                $values[] = $value instanceof Expression ? $this->expression($value, $context) : $context->bind($value);
            }
            $rows[] = '('.implode(', ', $values).')';
        }

        $quotedColumns = array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $columns);

        $on = [];
        foreach ($keys as $key) {
            $quoted = $this->dialect->quoteIdentifier($key);
            $on[] = 'target.'.$quoted.' = src.'.$quoted;
        }

        $set = [];
        foreach ($updateColumns as $column) {
            $quoted = $this->dialect->quoteIdentifier($column);
            $set[] = 'target.'.$quoted.' = src.'.$quoted;
        }

        $insertColumns = implode(', ', $quotedColumns);
        $insertValues = implode(', ', array_map(fn (string $column): string => 'src.'.$this->dialect->quoteIdentifier($column), $columns));

        $merge = 'merge into '.$table.' target using (values '.implode(', ', $rows).') src ('.$insertColumns.') on ('
            .implode(' and ', $on).') when matched then update set '.implode(', ', $set)
            .' when not matched then insert ('.$insertColumns.') values ('.$insertValues.')';

        // SQL Server requires the MERGE statement to be terminated with a semicolon.
        return $this->dialect->vendor() === DialectVendor::SqlServer ? $merge.';' : $merge;
    }

    private function update(UpdateQuery $query, CompileContext $context): string
    {
        $table = $this->dialect->quoteIdentifier($query->table()->qualifiedName());

        $fromSql = array_map(fn ($fromTable): string => $this->tableReference($fromTable), $query->fromTables());

        $sql = 'update '.$table.$this->dialect->updateFromBeforeSet($fromSql, $table);

        foreach ($query->joins() as $join) {
            $sql .= $this->join($join, $context, true);
        }

        $sets = [];
        foreach ($query->values() as $column => $value) {
            $quoted = $this->dialect->quoteIdentifier((string) $column);
            $sets[] = $quoted.' = '.($value instanceof Expression ? $this->expression($value, $context) : $context->bind($value));
        }

        if ($sets === []) {
            throw new CompilationException('UPDATE requires at least one SET value.');
        }

        $sql .= ' set '.implode(', ', $sets);
        $sql .= $this->dialect->outputClause($query->returningColumns(), 'update');

        if ($fromSql !== []) {
            $sql .= $this->dialect->updateFromClause($fromSql, $table);
        }

        if (($where = $query->whereExpression()) !== null) {
            $sql .= ' where '.$this->expression($where, $context);
        }

        if ($query->orders() !== []) {
            $sql .= $this->orderByClause($query->orders(), $context);
        }

        if ($query->limitValue() !== null) {
            $sql .= $this->dialect->updateDeleteLimit($query->limitValue());
        }

        return $sql.$this->returningSuffix($query);
    }

    private function delete(DeleteQuery $query, CompileContext $context): string
    {
        $sql = 'delete from '.$this->dialect->quoteIdentifier($query->table()->qualifiedName());
        $sql .= $this->dialect->outputClause($query->returningColumns(), 'delete');

        if (($where = $query->whereExpression()) !== null) {
            $sql .= ' where '.$this->expression($where, $context);
        }

        if ($query->orders() !== []) {
            $sql .= $this->orderByClause($query->orders(), $context);
        }

        if ($query->limitValue() !== null) {
            $sql .= $this->dialect->updateDeleteLimit($query->limitValue());
        }

        return $sql.$this->returningSuffix($query);
    }

    // ------------------------------------------------------------------
    // Clauses
    // ------------------------------------------------------------------

    private function fromSource(SelectQuery $query, CompileContext $context): string
    {
        if ($query->fromSubquery() !== null) {
            $alias = $query->tableAlias();
            if ($alias === null) {
                throw new CompilationException('A derived table requires an alias.');
            }

            return '('.$this->select($query->fromSubquery(), $context->nested()).') as '.$this->dialect->quoteIdentifier($alias);
        }

        if ($query->fromRawSource() !== null) {
            $raw = $this->rawExpression($query->fromRawSource(), $context);

            return $query->fromRawAlias() !== null
                ? $raw.' as '.$this->dialect->quoteIdentifier((string) $query->fromRawAlias())
                : $raw;
        }

        $table = $query->table();
        if ($table === null) {
            throw new CompilationException('SELECT requires a FROM source. Use DUAL-style selects through raw SQL when needed.');
        }

        $sql = $this->tableReference($table);
        if ($query->tableAlias() !== null && $query->tableAlias() !== $table->name) {
            $sql .= ' as '.$this->dialect->quoteIdentifier($query->tableAlias());
        }

        if (($hint = $query->indexHintValue()) !== null) {
            if (! $this->dialect->supportsIndexHint()) {
                throw new UnsupportedFeatureException(
                    feature: 'INDEX HINT',
                    engine: $this->dialect->vendor()->value,
                    engineVersion: $this->dialect->version()->raw,
                    reason: 'Index hints are only available on MySQL and MariaDB',
                    alternatives: ['Adjust the schema indexes', 'Rewrite the query to help the planner'],
                );
            }

            $sql .= ' '.$hint['type'].' index ('.$this->dialect->quoteIdentifier($hint['index']).')';
        }

        return $sql;
    }

    private function join(Join $join, CompileContext $context, bool $forUpdate = false): string
    {
        $keyword = match ($join->type) {
            JoinType::Inner => $join->straight ? $this->straightJoinKeyword($context) : ' join ',
            JoinType::Left => ' left join ',
            JoinType::Right => ' right join ',
            JoinType::Cross => ' cross join ',
            JoinType::Full => ' full join ',
            JoinType::LeftLateral, JoinType::InnerLateral => $this->lateralKeyword($join->type),
        };

        $table = $join->table instanceof SelectQuery
            ? '('.$this->select($join->table, $context->nested()).')'
            : $this->tableReference($join->table);

        if ($join->alias !== null) {
            $table .= ' as '.$this->dialect->quoteIdentifier($join->alias);
        } elseif ($join->table instanceof SelectQuery) {
            throw new CompilationException('A joined subquery requires an alias.');
        }

        $sql = $keyword.$table;

        if ($join->using !== []) {
            $sql .= ' using ('.implode(', ', array_map(fn (string $column): string => $this->dialect->quoteIdentifier($column), $join->using)).')';

            return $sql;
        }

        if ($join->on !== null) {
            $sql .= ' on '.$this->expression($join->on, $context);
        } elseif (in_array($join->type, [JoinType::LeftLateral, JoinType::InnerLateral], true) && $this->dialect->lateralJoinNeedsOn()) {
            $sql .= ' on true';
        }

        return $sql;
    }

    private function lateralKeyword(JoinType $type): string
    {
        $keyword = $this->dialect->lateralJoinKeyword($type);

        if ($keyword === null) {
            throw new UnsupportedFeatureException(
                feature: 'LATERAL JOIN',
                engine: $this->dialect->vendor()->value,
                engineVersion: $this->dialect->version()->raw,
                reason: 'LATERAL joins are not supported for this dialect/version',
                alternatives: ['Rewrite with joinSub()', 'Use a correlated EXISTS subquery'],
            );
        }

        return $keyword;
    }

    private function straightJoinKeyword(CompileContext $context): string
    {
        if ($this->dialect->supportsStraightJoin()) {
            return ' straight join ';
        }

        $context->warn(sprintf(
            'STRAIGHT_JOIN ignored on %s %s: compiled as a regular join (identical data).',
            $this->dialect->vendor()->value,
            $this->dialect->version()->raw,
        ));

        return ' join ';
    }

    /**
     * @param  list<Expression>  $columns
     */
    private function projections(array $columns, CompileContext $context): string
    {
        if ($columns === []) {
            return '*';
        }

        $parts = [];
        foreach ($columns as $column) {
            $sql = $this->expression($column, $context);
            if (($alias = $column->alias()) !== null) {
                $sql .= ' as '.$this->dialect->quoteIdentifier($alias);
            }
            $parts[] = $sql;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<Ordering>  $orders
     */
    private function orderByClause(array $orders, CompileContext $context): string
    {
        if ($orders === []) {
            return '';
        }

        $parts = [];
        foreach ($orders as $order) {
            $expression = $order->expression;
            if (is_string($expression)) {
                $sql = str_contains($expression, '(') || str_contains($expression, ' ')
                    ? $expression
                    : $this->dialect->quoteIdentifier($expression);
            } else {
                $sql = $this->expression($expression, $context);
            }

            $sql .= $order->direction === SortDirection::Desc ? ' desc' : ' asc';

            if ($order->nullsFirst !== null) {
                $sql .= $order->nullsFirst ? ' nulls first' : ' nulls last';
            }

            $parts[] = $sql;
        }

        return ' order by '.implode(', ', $parts);
    }

    private function limitOffset(SelectQuery $query, CompileContext $context, bool $hasOrder): string
    {
        $limit = $query->limitValue();
        $offset = $query->offsetValue();

        if ($limit === null && $offset === null) {
            return '';
        }

        return $this->dialect->limitOffset($limit, $offset, $hasOrder);
    }

    private function hasOrder(SelectQuery $query): bool
    {
        return $query->orders() !== [];
    }

    private function returningSuffix(InsertQuery|UpdateQuery|DeleteQuery $query): string
    {
        $columns = $query->returningColumns();
        if ($columns === null) {
            return '';
        }

        $clause = $this->dialect->returningClause($columns);

        return $clause === null ? '' : ' '.$clause;
    }

    private function upsertSuffix(InsertQuery $query): string
    {
        if (! $query->isUpsert()) {
            return '';
        }

        return ' '.$this->dialect->upsertSuffix($query, fn (string $column): string => $this->dialect->quoteIdentifier($column));
    }

    // ------------------------------------------------------------------
    // Expressions
    // ------------------------------------------------------------------

    private function expression(ExpressionInterface $expression, CompileContext $context): string
    {
        if (! $expression instanceof Expression) {
            throw new CompilationException('Unsupported expression ['.$expression::class.'].');
        }

        return match (true) {
            $expression instanceof Column => $this->columnReference($expression),
            $expression instanceof Value => $context->bind($expression->value),
            $expression instanceof Parameter => $context->placeholder($expression->name ?? 'param'),
            $expression instanceof RawExpression => $this->rawExpression($expression, $context),
            $expression instanceof Comparison => $this->comparison($expression, $context),
            $expression instanceof LogicalExpression => $this->logical($expression, $context),
            $expression instanceof Between => $this->between($expression, $context),
            $expression instanceof InList => $this->inList($expression, $context),
            $expression instanceof RowValues => $this->rowValues($expression, $context),
            $expression instanceof Exists => ($expression->negated ? 'not exists ' : 'exists ').'('.$this->select($expression->query, $context->nested()).')',
            $expression instanceof FullText => $this->dialect->compileFullText($expression->columns, $this->expression($expression->value, $context), $expression->options),
            $expression instanceof CaseExpression => $this->caseExpression($expression, $context),
            $expression instanceof Subquery => '('.$this->select($expression->query, $context->nested()).')',
            $expression instanceof AliasedExpression => $this->expression($expression->expression, $context).' as '.$this->dialect->quoteIdentifier((string) $expression->alias()),
            $expression instanceof WindowExpression => $this->window($expression, $context),
            $expression instanceof JsonExpression => $this->dialect->compileJson($this->expression($expression->expression, $context), $expression->path, $expression->asText),
            $expression instanceof JsonLengthExpression => $this->dialect->compileJsonLength($this->expression($expression->json->expression, $context), $expression->json->path),
            $expression instanceof DateExpression => $this->dialect->compileDatePart($expression->part, $this->expression($expression->expression, $context)),
            $expression instanceof VectorDistance => $this->vectorDistance($expression, $context),
            $expression instanceof Aggregate => $this->dialect->compileAggregate($expression, fn (Expression $argument): string => $this->expression($argument, $context), $context),
            $expression instanceof FunctionCall => $this->functionCall($expression, $context),
            default => throw new CompilationException('Unsupported expression node ['.$expression->nodeType().'].'),
        };
    }

    private function columnReference(Column $column): string
    {
        if ($column->name === '*') {
            return $column->table !== null ? $this->dialect->quoteIdentifier($column->table).'.*' : '*';
        }

        $name = $this->dialect->quoteIdentifier($column->name);

        return $column->table !== null ? $this->dialect->quoteIdentifier($column->table).'.'.$name : $name;
    }

    private function rawExpression(RawExpression $expression, CompileContext $context): string
    {
        $fragment = $expression->fragment;

        foreach ($fragment->bindings as $binding) {
            $context->bindings[] = $binding;
        }

        if ($fragment->type === RawType::Identifier && $context->strictIdentifiers) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $fragment->sql) !== 1) {
                throw QueryException::unsafeIdentifier($fragment->sql);
            }
        }

        return $fragment->sql;
    }

    private function comparison(Comparison $comparison, CompileContext $context): string
    {
        if ($comparison->left instanceof JsonExpression && $comparison->operator === ComparisonOperator::JsonOverlaps) {
            if ($comparison->right === null || ! $comparison->right instanceof Value) {
                throw new CompilationException('JSON overlaps requires a literal value.');
            }

            $left = $this->expression($comparison->left->expression, $context);
            $placeholder = $context->bind(json_encode($comparison->right->value, JSON_THROW_ON_ERROR));

            return $this->dialect->compileJsonOverlaps($left, $placeholder);
        }

        if ($comparison->left instanceof JsonExpression && in_array($comparison->operator, [ComparisonOperator::JsonContains, ComparisonOperator::JsonContainsKey], true)) {
            $json = $comparison->left;
            $left = $this->expression($json->expression, $context);

            if ($comparison->operator === ComparisonOperator::JsonContainsKey) {
                $key = $comparison->right instanceof Value ? (string) $comparison->right->value : '';
                $path = $json->path === '$' ? '$.'.$key : $json->path.'.'.$key;

                return $this->dialect->compileJsonContainsKey($left, $path);
            }

            if ($comparison->right === null || ! $comparison->right instanceof Value) {
                throw new CompilationException('JSON contains requires a literal value.');
            }

            $placeholder = $context->bind(json_encode($comparison->right->value, JSON_THROW_ON_ERROR));

            return $this->dialect->compileJsonContains($left, $json->path, $placeholder);
        }

        $left = $this->expression($comparison->left, $context);

        if ($comparison->operator === ComparisonOperator::IsNull) {
            return $left.' is null';
        }

        if ($comparison->operator === ComparisonOperator::IsNotNull) {
            return $left.' is not null';
        }

        if ($comparison->right === null) {
            throw new CompilationException('Comparison ['.$comparison->operator->value.'] requires a right hand side.');
        }

        $right = $this->expression($comparison->right, $context);

        return match ($comparison->operator) {
            ComparisonOperator::Eq => $left.' = '.$right,
            ComparisonOperator::Neq => $left.' <> '.$right,
            ComparisonOperator::Gt => $left.' > '.$right,
            ComparisonOperator::Gte => $left.' >= '.$right,
            ComparisonOperator::Lt => $left.' < '.$right,
            ComparisonOperator::Lte => $left.' <= '.$right,
            ComparisonOperator::Like => $left.' like '.$right,
            ComparisonOperator::NotLike => $left.' not like '.$right,
            ComparisonOperator::ILike => $this->dialect->compileIlike($left, $right),
            ComparisonOperator::Regex => $this->dialect->compileRegex($left, $right),
            ComparisonOperator::IsDistinctFrom => $this->dialect->compileIsDistinctFrom($left, $right, false),
            ComparisonOperator::IsNotDistinctFrom => $this->dialect->compileIsDistinctFrom($left, $right, true),
            ComparisonOperator::BinaryEq => $this->binaryComparison($left, $right, false, $context),
            ComparisonOperator::BinaryNeq => $this->binaryComparison($left, $right, true, $context),
            ComparisonOperator::LikeBinary => $this->binaryLike($left, $right, false),
            ComparisonOperator::NotLikeBinary => $this->binaryLike($left, $right, true),
            default => throw new CompilationException('Unsupported comparison operator ['.$comparison->operator->value.'].'),
        };
    }

    private function binaryLike(string $left, string $right, bool $negated): string
    {
        if (! $this->dialect->supportsBinaryComparison()) {
            throw new UnsupportedFeatureException(
                feature: 'case sensitive LIKE',
                engine: $this->dialect->vendor()->value,
                engineVersion: $this->dialect->version()->raw,
                reason: 'Case sensitive LIKE is only available on MySQL and MariaDB',
                alternatives: ['Compare lowercased values', 'Use a case sensitive collation in the column'],
            );
        }

        return $left.($negated ? ' not like binary ' : ' like binary ').$right;
    }

    private function vectorDistance(VectorDistance $vectorDistance, CompileContext $context): string
    {
        if (! $this->dialect->supportsVectorDistance()) {
            throw new UnsupportedFeatureException(
                feature: 'VECTOR DISTANCE',
                engine: $this->dialect->vendor()->value,
                engineVersion: $this->dialect->version()->raw,
                reason: 'Vector distance requires MariaDB 11.7+ or PostgreSQL with pgvector',
                alternatives: ['Filter in the application layer', 'Use dedicated search infrastructure (e.g. vector database)'],
            );
        }

        $placeholder = $context->bind($vectorDistance->vector->value);

        return $this->dialect->compileVectorDistance($this->expression($vectorDistance->column, $context), $placeholder);
    }

    private function binaryComparison(string $left, string $right, bool $negated, CompileContext $context): string
    {
        if (! $this->dialect->supportsBinaryComparison()) {
            throw new UnsupportedFeatureException(
                feature: 'BINARY comparison',
                engine: $this->dialect->vendor()->value,
                engineVersion: $this->dialect->version()->raw,
                reason: 'Case sensitive binary comparison is only available on MySQL and MariaDB',
                alternatives: ['Compare lowercased values', 'Use a case sensitive collation in the column'],
            );
        }

        return $left.($negated ? ' != binary ' : ' = binary ').$right;
    }

    private function logical(LogicalExpression $logical, CompileContext $context): string
    {
        $parts = [];

        foreach ($logical->parts() as $part) {
            // Flatten same-operator groups: `a and b and c` keeps golden SQL stable
            // and is semantically identical for AND/OR (associative operators).
            if ($part instanceof LogicalExpression
                && $part->operator === $logical->operator
                && in_array($logical->operator, [LogicalOperator::And, LogicalOperator::Or], true)
            ) {
                foreach ($part->parts() as $nested) {
                    $parts[] = $this->logicalPart($nested, $context);
                }

                continue;
            }

            $parts[] = $this->logicalPart($part, $context);
        }

        if ($parts === []) {
            return '1 = 1';
        }

        if ($logical->operator === LogicalOperator::Not) {
            return 'not ('.implode(' and ', $parts).')';
        }

        return implode(' '.$logical->operator->value.' ', $parts);
    }

    private function logicalPart(Expression $part, CompileContext $context): string
    {
        $sql = $this->expression($part, $context);

        return $part instanceof LogicalExpression ? '('.$sql.')' : $sql;
    }

    private function between(Between $between, CompileContext $context): string
    {
        return $this->expression($between->expression, $context)
            .($between->negated ? ' not between ' : ' between ')
            .$this->expression($between->low, $context)
            .' and '
            .$this->expression($between->high, $context);
    }

    private function inList(InList $in, CompileContext $context): string
    {
        $left = $this->expression($in->expression, $context);

        if ($in->values instanceof SelectQuery) {
            return $left.($in->negated ? ' not in ' : ' in ').'('.$this->select($in->values, $context->nested()).')';
        }

        if ($in->values === []) {
            // Empty IN is always false / NOT IN always true; compiled portably.
            return $in->negated ? '1 = 1' : '1 = 0';
        }

        $placeholders = [];
        foreach ($in->values as $value) {
            $placeholders[] = $value instanceof Expression
                ? $this->expression($value, $context)
                : $context->bind($value);
        }

        return $left.($in->negated ? ' not in (' : ' in (').implode(', ', $placeholders).')';
    }

    private function rowValues(RowValues $rowValues, CompileContext $context): string
    {
        $columns = [];
        foreach ($rowValues->columns as $column) {
            $columns[] = $this->expression($column, $context);
        }

        $values = [];
        foreach ($rowValues->values as $value) {
            $values[] = $this->expression($value, $context);
        }

        return '('.implode(', ', $columns).') '.$rowValues->operator->value.' ('.implode(', ', $values).')';
    }

    private function caseExpression(CaseExpression $case, CompileContext $context): string
    {
        $sql = 'case';

        if ($case->case !== null) {
            $sql .= ' '.$this->expression($case->case, $context);
        }

        foreach ($case->whens as [$condition, $result]) {
            $sql .= ' when '.$this->expression($condition, $context).' then '.$this->expression($result, $context);
        }

        if ($case->else !== null) {
            $sql .= ' else '.$this->expression($case->else, $context);
        }

        return $sql.' end';
    }

    private function window(WindowExpression $window, CompileContext $context): string
    {
        if ($window->window !== null) {
            $inner = $window->window;
            $name = $inner->windowName ?? $inner->alias();

            if ($name !== null) {
                return $this->expression($window->function, $context).' over '.$this->dialect->quoteIdentifier($name);
            }

            return $this->expression($window->function, $context).' over ('.$this->windowSpecification($inner, $context).')';
        }

        if ($window->windowName !== null) {
            return $this->expression($window->function, $context).' over '.$this->dialect->quoteIdentifier($window->windowName);
        }

        return $this->expression($window->function, $context).' over ('.$this->windowSpecification($window, $context).')';
    }

    private function windowSpecification(WindowExpression $window, CompileContext $context): string
    {
        $parts = [];

        if ($window->partitionBy !== []) {
            $parts[] = 'partition by '.implode(', ', array_map(fn (Expression $part): string => $this->expression($part, $context), $window->partitionBy));
        }

        if ($window->orderBy !== []) {
            $parts[] = trim($this->orderByClause($window->orderBy, $context));
        }

        $sql = implode(' ', $parts);

        if ($window->frame !== null) {
            $sql .= $sql === '' ? $window->frame : ' '.$window->frame;
        }

        return $sql;
    }

    private function functionCall(FunctionCall $function, CompileContext $context): string
    {
        $arguments = [];
        foreach ($function->arguments as $argument) {
            $arguments[] = $argument instanceof Expression
                ? $this->expression($argument, $context)
                : (is_scalar($argument) || $argument === null ? $context->bind($argument) : throw new CompilationException('Invalid function argument.'));
        }

        $name = $this->dialect->functionName($function->name);
        $distinct = $function->distinct ? 'distinct ' : '';

        return $name.'('.$distinct.implode(', ', $arguments).')';
    }

    private function tableReference(TableIdentifier|string $table): string
    {
        $identifier = is_string($table) ? TableIdentifier::fromString($table) : $table;

        return $this->dialect->quoteIdentifier($identifier->qualifiedName());
    }

    public function dialect(): AbstractDialect
    {
        return $this->dialect;
    }
}
