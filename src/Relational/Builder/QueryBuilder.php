<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Builder;

use Closure;
use DateTimeInterface;
use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\CursorInterface;
use OrmUniversal\Contracts\EntityInterface;
use OrmUniversal\Contracts\Enums\AggregateFunction;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;
use OrmUniversal\Contracts\PaginatorInterface;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\RecordsetInterface;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\Ordering;
use OrmUniversal\Contracts\ValueObjects\PerformanceReport;
use OrmUniversal\Contracts\ValueObjects\PortabilityReport;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use OrmUniversal\Relational\Ast\Aggregate;
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
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\JsonLengthExpression;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\PredicateGroup;
use OrmUniversal\Relational\Ast\RawExpression;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Ast\RowValues;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\Subquery;
use OrmUniversal\Relational\Ast\UpdateQuery;
use OrmUniversal\Relational\Ast\VectorDistance;
use OrmUniversal\Relational\Ast\WindowExpression;
use OrmUniversal\Relational\Cache\ResultCache;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Execution\BatchPlanner;
use OrmUniversal\Relational\Execution\DeferredResult;
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Hydration\Hydrator;
use OrmUniversal\Relational\Optimizer\PerformanceAnalyzer;
use OrmUniversal\Relational\Optimizer\PortabilityAnalyzer;
use OrmUniversal\Relational\Recordset\ArrayRecordset;
use OrmUniversal\Relational\Recordset\StreamingRecordset;
use OrmUniversal\Relational\Relation\RelationAggregateCompiler;
use OrmUniversal\Relational\Relation\RelationDefinition;
use OrmUniversal\Relational\Relation\RelationRegistry;
use OrmUniversal\Relational\Result\Paginator;
use OrmUniversal\Relational\Support\Macroable;
use OrmUniversal\Relational\Type\CastApplier;

/**
 * Fluent query builder. Laravel style predicates and the object oriented
 * expression API converge into the same SelectQuery AST.
 *
 * @api public
 */
final class QueryBuilder implements QueryObjectInterface
{
    use Macroable;

    private Hydrator $hydrator;

    /** @var array<string, mixed> */
    private array $parameters = [];

    private ?DestructiveStatementPolicy $policy = null;

    private ?ResultCache $resultCache = null;

    private ?int $resultTtl = null;

    private bool $confirmedDestructive = false;

    private ?int $timeoutSeconds = null;

    private ?CastApplier $castApplier = null;

    public function __construct(
        private readonly QueryExecutor $executor,
        private SelectQuery $query,
        ?Hydrator $hydrator = null,
    ) {
        $this->hydrator = $hydrator ?? Hydrator::zero();
    }

    public static function table(QueryExecutor $executor, string $table, ?Hydrator $hydrator = null): self
    {
        return new self($executor, SelectQuery::make($table), $hydrator);
    }

    public function query(): SelectQuery
    {
        return $this->query;
    }

    public function executor(): QueryExecutor
    {
        return $this->executor;
    }

    public function __clone(): void
    {
        $this->query = clone $this->query;
    }

    // ------------------------------------------------------------------
    // Hydration modes
    // ------------------------------------------------------------------

    public function hydrateWith(Hydrator $hydrator): self
    {
        $this->hydrator = $hydrator;

        return $this;
    }

    /**
     * Apply cast definitions to fetched rows before hydration. Decimals stay
     * strings; JSON-like casts decode and enums resolve through their class.
     *
     * @param  array<string, mixed>  $casts
     */
    public function castWith(array $casts): self
    {
        $this->castApplier = $casts === [] ? null : CastApplier::for($casts);

        return $this;
    }

    public function light(): self
    {
        return $this->hydrateWith(Hydrator::light());
    }

    public function zeroHydration(): self
    {
        return $this->hydrateWith(Hydrator::zero());
    }

    /**
     * @param  class-string  $class
     */
    public function entity(string $class): self
    {
        return $this->hydrateWith(Hydrator::entity($class));
    }

    /**
     * @param  callable(array<string, mixed>): object  $factory
     */
    public function models(callable $factory): self
    {
        return $this->hydrateWith(Hydrator::factory($factory));
    }

    // ------------------------------------------------------------------
    // Parameters
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function using(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    // ------------------------------------------------------------------
    // Safety policy and inspection API
    // ------------------------------------------------------------------

    public function usingPolicy(DestructiveStatementPolicy $policy): self
    {
        $this->policy = $policy;

        return $this;
    }

    public function confirmDestructive(): self
    {
        $this->confirmedDestructive = true;

        return $this;
    }

    private function destructivePolicy(): DestructiveStatementPolicy
    {
        return $this->policy ??= new DestructiveStatementPolicy(DestructiveStatementPolicy::WARN);
    }

    /**
     * Eloquent compatibility alias for models().
     *
     * @param  callable(array<string, mixed>): object  $factory
     */
    public function eloquent(callable $factory): self
    {
        return $this->models($factory);
    }

    public function ast(): AstNodeInterface
    {
        return $this->query->toAst();
    }

    public function dialect(): AbstractDialect
    {
        return $this->executor->dialect();
    }

    public function capabilities(): CapabilitySet
    {
        return $this->executor->dialect()->capabilities();
    }

    /**
     * Portability report: portable / partially portable / dialect specific.
     */
    public function portability(): PortabilityReport
    {
        return (new PortabilityAnalyzer)->analyze($this->query->toAst());
    }

    /**
     * Static performance assessment before executing anything.
     */
    public function performance(): PerformanceReport
    {
        return (new PerformanceAnalyzer)->analyze($this->query, $this->hydrator->mode());
    }

    /**
     * Engine EXPLAIN when the dialect supports it. Never fakes a plan.
     *
     * @return list<array<string, mixed>>
     */
    public function explain(): array
    {
        if (! $this->dialect()->supports(Capability::EXPLAIN)) {
            throw new UnsupportedFeatureException(
                feature: 'EXPLAIN',
                engine: $this->dialect()->vendor()->value,
                engineVersion: $this->dialect()->version()->raw,
                reason: 'The dialect does not expose EXPLAIN through the portable API',
                alternatives: ['Run the engine CLI explain manually', 'Use ude:query:inspect --explain on a supported engine'],
            );
        }

        $compiled = $this->compile();

        return $this->timedRows(new RawQuery('explain '.$compiled->sql, $compiled->bindings));
    }

    // ------------------------------------------------------------------
    // Query building (delegates to the AST)
    // ------------------------------------------------------------------

    public function select(Expression|string ...$columns): self
    {
        $this->query->select(...$columns);

        return $this;
    }

    public function addSelect(Expression|string ...$columns): self
    {
        $this->query->addSelect(...$columns);

        return $this;
    }

    /**
     * Raw projection with explicit bindings. Values never travel inline.
     *
     * @param  list<mixed>  $bindings
     */
    public function selectRaw(string $sql, array $bindings = []): self
    {
        return $this->addSelect(RawExpression::parameterized($sql, $bindings));
    }

    /**
     * Scalar subquery projection: (select ...) as alias.
     */
    public function selectSub(QueryBuilder|SelectQuery $query, string $alias): self
    {
        return $this->addSelect((new Subquery($query instanceof QueryBuilder ? $query->query() : $query))->as($alias));
    }

    /**
     * Replace the FROM source. Subqueries require an alias.
     */
    public function fromSubquery(QueryBuilder|SelectQuery $query, string $alias): self
    {
        $this->query->from($query instanceof QueryBuilder ? $query->query() : $query, $alias);

        return $this;
    }

    /**
     * Set the FROM table (useful for builders created without a source).
     */
    public function fromTable(string $table, ?string $alias = null): self
    {
        $this->query->from($table, $alias);

        return $this;
    }

    public function distinct(bool $distinct = true): self
    {
        $this->query->distinct($distinct);

        return $this;
    }

    public function where(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->where($column, $operator, $value);

        return $this;
    }

    public function orWhere(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        $this->query->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Raw predicate with explicit bindings.
     *
     * @param  list<mixed>  $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        return $this->where(RawExpression::parameterized($sql, $bindings));
    }

    /**
     * @param  list<mixed>|QueryBuilder|SelectQuery  $values
     */
    public function whereIn(string|Expression $column, array|QueryBuilder|SelectQuery $values): self
    {
        $this->query->whereIn($column, $values instanceof QueryBuilder ? $values->query() : $values);

        return $this;
    }

    /**
     * @param  list<mixed>|QueryBuilder|SelectQuery  $values
     */
    public function whereNotIn(string|Expression $column, array|QueryBuilder|SelectQuery $values): self
    {
        $this->query->whereNotIn($column, $values instanceof QueryBuilder ? $values->query() : $values);

        return $this;
    }

    public function whereInSub(string|Expression $column, QueryBuilder|SelectQuery $query): self
    {
        return $this->whereIn($column, $query);
    }

    public function whereNotInSub(string|Expression $column, QueryBuilder|SelectQuery $query): self
    {
        return $this->whereNotIn($column, $query);
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($column);

        return $this;
    }

    public function whereBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->whereBetween($column, $low, $high);

        return $this;
    }

    public function whereNotBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->whereNotBetween($column, $low, $high);

        return $this;
    }

    /**
     * EXISTS predicate. Closures receive a fresh builder without a FROM source;
     * call fromTable()/fromSubquery() inside.
     */
    public function whereExists(Closure|QueryBuilder|SelectQuery $query, bool $negated = false): self
    {
        if ($query instanceof Closure) {
            $sub = new self($this->executor, SelectQuery::make());
            $query($sub);
            $this->query->whereExists($sub->query(), $negated);

            return $this;
        }

        $this->query->whereExists($query instanceof QueryBuilder ? $query->query() : $query, $negated);

        return $this;
    }

    public function whereNotExists(Closure|QueryBuilder|SelectQuery $query): self
    {
        return $this->whereExists($query, true);
    }

    public function whereColumn(string $first, string $operator, string $second): self
    {
        $this->query->whereColumn($first, $operator, $second);

        return $this;
    }

    public function join(string $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->join($table, $first, $operator, $value);

        return $this;
    }

    public function leftJoin(string $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->leftJoin($table, $first, $operator, $value);

        return $this;
    }

    public function rightJoin(string $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->rightJoin($table, $first, $operator, $value);

        return $this;
    }

    public function joinSub(QueryBuilder|SelectQuery $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->joinSub($query instanceof QueryBuilder ? $query->query() : $query, $alias, $first, $operator, $value);

        return $this;
    }

    public function leftJoinSub(QueryBuilder|SelectQuery $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->leftJoinSub($query instanceof QueryBuilder ? $query->query() : $query, $alias, $first, $operator, $value);

        return $this;
    }

    public function rightJoinSub(QueryBuilder|SelectQuery $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->rightJoinSub($query instanceof QueryBuilder ? $query->query() : $query, $alias, $first, $operator, $value);

        return $this;
    }

    public function joinLateral(QueryBuilder|SelectQuery $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->joinLateral($query instanceof QueryBuilder ? $query->query() : $query, $alias, $first, $operator, $value);

        return $this;
    }

    public function leftJoinLateral(QueryBuilder|SelectQuery $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->leftJoinLateral($query instanceof QueryBuilder ? $query->query() : $query, $alias, $first, $operator, $value);

        return $this;
    }

    public function groupBy(Expression|string ...$columns): self
    {
        $this->query->groupBy(...$columns);

        return $this;
    }

    /**
     * Raw GROUP BY expression with explicit bindings.
     *
     * @param  list<mixed>  $bindings
     */
    public function groupByRaw(string $sql, array $bindings = []): self
    {
        $this->query->groupBy(RawExpression::parameterized($sql, $bindings));

        return $this;
    }

    public function having(Expression|Closure|string $column, ?string $operator = null, mixed $value = null): self
    {
        if (is_string($column)) {
            if ($operator === null) {
                throw new \InvalidArgumentException('having() with a string column requires an operator; pass an Expression or Closure otherwise.');
            }

            $this->query->having(PredicateGroup::predicate($column, $operator, $value));

            return $this;
        }

        $this->query->having($column);

        return $this;
    }

    public function orHaving(Expression|Closure|string $column, ?string $operator = null, mixed $value = null): self
    {
        if (is_string($column)) {
            if ($operator === null) {
                throw new \InvalidArgumentException('orHaving() with a string column requires an operator; pass an Expression or Closure otherwise.');
            }

            $this->query->orHaving(PredicateGroup::predicate($column, $operator, $value));

            return $this;
        }

        $this->query->orHaving($column);

        return $this;
    }

    /**
     * Raw HAVING with explicit bindings.
     *
     * @param  list<mixed>  $bindings
     */
    public function havingRaw(string $sql, array $bindings = []): self
    {
        $this->query->having(RawExpression::parameterized($sql, $bindings));

        return $this;
    }

    public function orderBy(Ordering|string|Expression $column, ?string $direction = null): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function orderByDesc(Ordering|string|Expression $column): self
    {
        $this->query->orderByDesc($column);

        return $this;
    }

    /**
     * Raw ORDER BY expression with explicit bindings.
     *
     * @param  list<mixed>  $bindings
     */
    public function orderByRaw(string $sql, array $bindings = [], string $direction = 'asc'): self
    {
        $this->query->orderBy(RawExpression::parameterized($sql, $bindings), $direction);

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        $this->query->latest($column);

        return $this;
    }

    public function oldest(string $column = 'created_at'): self
    {
        $this->query->oldest($column);

        return $this;
    }

    public function limit(?int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    public function take(int $limit): self
    {
        $this->query->take($limit);

        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->query->offset($offset);

        return $this;
    }

    public function skip(int $offset): self
    {
        $this->query->skip($offset);

        return $this;
    }

    public function union(QueryBuilder|SelectQuery $query, bool $all = false): self
    {
        $this->query->union($query instanceof QueryBuilder ? $query->query() : $query, $all);

        return $this;
    }

    public function unionAll(QueryBuilder|SelectQuery $query): self
    {
        $this->query->unionAll($query instanceof QueryBuilder ? $query->query() : $query);

        return $this;
    }

    public function crossJoin(string $table): self
    {
        $this->query->crossJoin($table);

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function with(string $name, QueryBuilder|SelectQuery $query, bool $recursive = false, array $columns = []): self
    {
        $this->query->with($name, $query instanceof QueryBuilder ? $query->query() : $query, $recursive, $columns);

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function withRecursive(string $name, QueryBuilder|SelectQuery $query, array $columns = []): self
    {
        return $this->with($name, $query, true, $columns);
    }

    public function lock(string $mode = 'for update'): self
    {
        $this->query->lock($mode);

        return $this;
    }

    /**
     * Opt-in result cache. Requires usingResultCache() plus remember(ttl).
     */
    public function usingResultCache(ResultCache $cache): self
    {
        $this->resultCache = $cache;

        return $this;
    }

    /**
     * Cache the next read for the given TTL (opt-in; bypassed in transactions).
     */
    public function remember(int $ttl = 60): self
    {
        $this->resultTtl = $ttl;

        return $this;
    }

    /**
     * Apply a composable specification without executing anything.
     */
    public function apply(object $specification): self
    {
        $specification->apply($this);

        return $this;
    }

    // ------------------------------------------------------------------
    // Relations, aggregates and Eloquent-style helpers
    // ------------------------------------------------------------------

    private ?RelationRegistry $relations = null;

    public function usingRelations(RelationRegistry $registry): self
    {
        $this->relations = $registry;

        return $this;
    }

    /**
     * @param  RelationDefinition|string|array<int|string, mixed>  $relations
     */
    public function withCount(RelationDefinition|string|array $relations): self
    {
        foreach ($this->normalizeRelationList($relations) as [$relation, $constraints]) {
            $this->addRelationAggregate($relation, 'count', '*', $constraints, null);
        }

        return $this;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withSum(RelationDefinition|string $relation, string $column, ?Closure $constraints = null, ?string $alias = null): self
    {
        return $this->addRelationAggregate($relation, 'sum', $column, $constraints, $alias);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withAvg(RelationDefinition|string $relation, string $column, ?Closure $constraints = null, ?string $alias = null): self
    {
        return $this->addRelationAggregate($relation, 'avg', $column, $constraints, $alias);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withMin(RelationDefinition|string $relation, string $column, ?Closure $constraints = null, ?string $alias = null): self
    {
        return $this->addRelationAggregate($relation, 'min', $column, $constraints, $alias);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withMax(RelationDefinition|string $relation, string $column, ?Closure $constraints = null, ?string $alias = null): self
    {
        return $this->addRelationAggregate($relation, 'max', $column, $constraints, $alias);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withExists(RelationDefinition|string $relation, ?Closure $constraints = null, ?string $alias = null): self
    {
        $definition = $this->resolveRelation($relation);
        $alias ??= self::snake($definition->name()).'_exists';

        $this->ensureSelectBase();
        $this->query->addSelect(RelationAggregateCompiler::exists($definition, $this->requireTableName(), $constraints, false, $alias));

        return $this;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withAggregate(RelationDefinition|string $relation, string $column, string $function, ?Closure $constraints = null, ?string $alias = null, bool $distinct = false): self
    {
        return $this->addRelationAggregate($relation, $function, $column, $constraints, $alias, $distinct);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function has(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        return $this->whereHas($relation, $constraints);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function orHas(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        return $this->orWhereHas($relation, $constraints);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function doesntHave(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        return $this->whereDoesntHave($relation, $constraints);
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function orDoesntHave(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        $definition = $this->resolveRelation($relation);

        $this->query->orWhere(RelationAggregateCompiler::exists($definition, $this->requireTableName(), $constraints, true));

        return $this;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function whereHas(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        $definition = $this->resolveRelation($relation);

        $this->query->where(RelationAggregateCompiler::exists($definition, $this->requireTableName(), $constraints, false));

        return $this;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function orWhereHas(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        $definition = $this->resolveRelation($relation);

        $this->query->orWhere(RelationAggregateCompiler::exists($definition, $this->requireTableName(), $constraints, false));

        return $this;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function whereDoesntHave(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        $definition = $this->resolveRelation($relation);

        $this->query->where(RelationAggregateCompiler::exists($definition, $this->requireTableName(), $constraints, true));

        return $this;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    public function withWhereHas(RelationDefinition|string $relation, ?Closure $constraints = null): self
    {
        return $this->whereHas($relation, $constraints);
    }

    public function sum(string $column): int|float|string|null
    {
        return $this->aggregateValue('sum', $column);
    }

    public function avg(string $column): int|float|string|null
    {
        return $this->aggregateValue('avg', $column);
    }

    public function min(string $column): int|float|string|null
    {
        return $this->aggregateValue('min', $column);
    }

    public function max(string $column): int|float|string|null
    {
        return $this->aggregateValue('max', $column);
    }

    public function valueOrFail(string $column): mixed
    {
        $value = $this->value($column);

        if ($value === null) {
            throw new QueryException('No result for valueOrFail on column ['.$column.'].');
        }

        return $value;
    }

    public function existsOr(mixed $value): mixed
    {
        return $this->exists() ? true : $value;
    }

    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    public function doesntExistOr(mixed $value): mixed
    {
        return $this->doesntExist() ? true : $value;
    }

    public function sole(): mixed
    {
        $count = $this->count();

        if ($count === 0) {
            throw new QueryException('No records found for sole().');
        }

        if ($count > 1) {
            throw new QueryException('Multiple records found for sole() ('.$count.').');
        }

        return $this->first();
    }

    public function firstOrNew(array $attributes, array $values = []): array
    {
        return array_merge($attributes, $values);
    }

    public function firstOrCreate(array $attributes, array $values = []): array
    {
        $this->applyAttributeWheres($attributes);
        $found = $this->first();

        if (is_array($found)) {
            return $found;
        }

        return $this->create(array_merge($attributes, $values));
    }

    public function createOrFirst(array $attributes, array $values = []): array
    {
        return $this->firstOrCreate($attributes, $values);
    }

    public function updateOrCreate(array $attributes, array $values = []): array
    {
        $this->applyAttributeWheres($attributes);
        $found = $this->first();

        if (is_array($found)) {
            $builder = self::table($this->executor, $this->requireTableName())->using($this->parameters);
            $builder->applyAttributeWheres($attributes);
            $builder->update($values);

            return array_merge($found, $values);
        }

        return $this->create(array_merge($attributes, $values));
    }

    public function create(array $values): array
    {
        $this->insert([$values]);

        $id = $this->executor->connection()->lastInsertId();

        return array_merge($values, ['id' => is_numeric($id) ? (int) $id : $id]);
    }

    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $quoted = $this->dialect()->quoteIdentifier($column);

        return $this->update(array_merge($extra, [
            $column => RawExpression::parameterized($quoted.' + ?', [$amount]),
        ]));
    }

    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        $quoted = $this->dialect()->quoteIdentifier($column);

        return $this->update(array_merge($extra, [
            $column => RawExpression::parameterized($quoted.' - ?', [$amount]),
        ]));
    }

    public function whereNot(string $column, mixed $value): self
    {
        $this->query->where(Column::make($column)->neq($value));

        return $this;
    }

    /**
     * @param  list<mixed>  $values
     */
    public function orWhereIn(string $column, array $values): self
    {
        $this->query->orWhere(Column::make($column)->in($values));

        return $this;
    }

    /**
     * @param  list<mixed>  $values
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        $this->query->orWhere(Column::make($column)->notIn($values));

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        $this->query->orWhere(Column::make($column)->isNull());

        return $this;
    }

    public function orWhereNotNull(string $column): self
    {
        $this->query->orWhere(Column::make($column)->isNotNull());

        return $this;
    }

    public function orWhereBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->orWhere(Column::make($column)->between($low, $high));

        return $this;
    }

    public function whereYear(string $column, int|string $value): self
    {
        $this->query->where((new DateExpression('year', Column::make($column)))->eq($value));

        return $this;
    }

    public function whereMonth(string $column, int|string $value): self
    {
        $this->query->where((new DateExpression('month', Column::make($column)))->eq($value));

        return $this;
    }

    public function whereDay(string $column, int|string $value): self
    {
        $this->query->where((new DateExpression('day', Column::make($column)))->eq($value));

        return $this;
    }

    public function whereJsonContains(string $column, mixed $value): self
    {
        $this->query->where(Expression::json(Column::make($column), '$')->contains($value));

        return $this;
    }

    /**
     * Cursor (keyset) pagination. The next cursor is the last item's column value.
     */
    public function cursorPaginate(int $perPage = 15, string $column = 'id', string $direction = 'desc', mixed $cursor = null): PaginatorInterface
    {
        $perPage = max(1, $perPage);
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $builder = clone $this;
        $builder->query->clearOrderings()->orderBy($column, $direction);

        if ($cursor !== null) {
            $builder->query->where(Column::make($column), $direction === 'desc' ? '<' : '>', $cursor);
        }

        $rows = $builder->limit($perPage + 1)->get();
        $hasMore = count($rows) > $perPage;
        $items = array_slice($rows, 0, $perPage);

        return new Paginator($items, $hasMore ? null : count($items), $perPage, 1);
    }

    /**
     * Keyset pagination (no offset drift). Iterates in ascending order.
     */
    public function chunkById(int $size, callable $callback, string $column = 'id'): bool
    {
        $page = 1;
        $last = null;

        while (true) {
            $builder = self::table($this->executor, $this->requireTableName())
                ->using($this->parameters)
                ->orderBy($column);

            if ($last !== null) {
                $builder->where($column, '>', $last);
            }

            $chunk = $builder->limit($size)->get();

            if ($chunk === []) {
                return true;
            }

            if ($callback($chunk, $page++) === false) {
                return false;
            }

            $lastRow = $chunk[count($chunk) - 1];
            $last = is_array($lastRow) ? ($lastRow[$column] ?? null) : ($lastRow->{$column} ?? null);

            if (count($chunk) < $size) {
                return true;
            }
        }
    }

    public function eachById(callable $callback, int $size = 1000, string $column = 'id'): bool
    {
        return $this->chunkById($size, function (array $chunk) use ($callback): void {
            foreach ($chunk as $row) {
                $callback($row);
            }
        }, $column);
    }

    /**
     * @return \Generator<int, mixed>
     */
    public function lazyById(int $size = 1000, string $column = 'id'): \Generator
    {
        $last = null;

        while (true) {
            $builder = self::table($this->executor, $this->requireTableName())
                ->using($this->parameters)
                ->orderBy($column);

            if ($last !== null) {
                $builder->where($column, '>', $last);
            }

            $chunk = $builder->limit($size)->get();

            if ($chunk === []) {
                return;
            }

            foreach ($chunk as $row) {
                yield $row;
            }

            $lastRow = $chunk[count($chunk) - 1];
            $last = is_array($lastRow) ? ($lastRow[$column] ?? null) : ($lastRow->{$column} ?? null);

            if (count($chunk) < $size) {
                return;
            }
        }
    }

    private function aggregateValue(string $function, string $column): int|float|string|null
    {
        $query = clone $this->query;
        $query->clearOrderings()->clearLimitOffset();

        $aggregate = match (strtolower($function)) {
            'sum' => new Aggregate(AggregateFunction::Sum, Column::make($column)),
            'avg' => new Aggregate(AggregateFunction::Avg, Column::make($column)),
            'min' => new Aggregate(AggregateFunction::Min, Column::make($column)),
            'max' => new Aggregate(AggregateFunction::Max, Column::make($column)),
            default => throw new QueryException('Unsupported aggregate ['.$function.'].'),
        };

        $query->select($aggregate);

        $value = $this->timedValue($query, $this->parameters);

        return $value === false ? null : $value;
    }

    /**
     * @param  Closure(SelectQuery): void|null  $constraints
     */
    private function addRelationAggregate(RelationDefinition|string $relation, string $function, string $column, ?Closure $constraints, ?string $alias, bool $distinct = false): self
    {
        $definition = $this->resolveRelation($relation);
        $alias ??= self::snake($definition->name()).'_'.$function.($column === '*' ? '' : '_'.$column);

        $this->ensureSelectBase();

        $this->query->addSelect(RelationAggregateCompiler::aggregate(
            $definition,
            $this->requireTableName(),
            $function,
            $column,
            $constraints,
            $distinct,
            $alias,
        ));

        return $this;
    }

    private function resolveRelation(RelationDefinition|string $relation): RelationDefinition
    {
        if ($relation instanceof RelationDefinition) {
            return $relation;
        }

        $registry = $this->relations
            ?? throw new QueryException('withCount/whereHas por nombre requieren usingRelations(RelationRegistry) o pasar un RelationDefinition.');

        return $registry->get($this->requireTableName(), $relation);
    }

    /**
     * @param  RelationDefinition|string|array<int|string, mixed>  $relations
     * @return list<array{0: RelationDefinition|string, 1: Closure|null}>
     */
    private function normalizeRelationList(RelationDefinition|string|array $relations): array
    {
        if (! is_array($relations)) {
            return [[$relations, null]];
        }

        $normalized = [];
        foreach ($relations as $key => $value) {
            if (is_int($key)) {
                $normalized[] = [$value, null];

                continue;
            }

            $normalized[] = [$key, is_callable($value) ? $value : null];
        }

        return $normalized;
    }

    private static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }

    private function ensureSelectBase(): void
    {
        if ($this->query->columns() === []) {
            $this->query->select(RawExpression::trusted('*'));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyAttributeWheres(array $attributes): void
    {
        foreach ($attributes as $column => $value) {
            $this->query->where((string) $column, '=', $value);
        }
    }

    // ------------------------------------------------------------------
    // Execution
    // ------------------------------------------------------------------

    /**
     * @return list<mixed>
     */
    public function get(): array
    {
        if ($this->resultCache === null || $this->resultTtl === null) {
            $rows = $this->timedRows($this->query, $this->parameters);
            $rows = $this->castApplier === null ? $rows : $this->castApplier->applyMany($rows);

            return iterator_to_array($this->hydrator->hydrateMany($rows), false);
        }

        $compiled = $this->compile();
        $key = $this->resultCache->key($this->dialect(), $this->connectionName(), null, $compiled, $this->parameters);

        /** @var list<mixed> $result */
        $result = $this->resultCache->remember($key, function (): array {
            $rows = $this->timedRows($this->query, $this->parameters);
            $rows = $this->castApplier === null ? $rows : $this->castApplier->applyMany($rows);

            return iterator_to_array($this->hydrator->hydrateMany($rows), false);
        }, $this->resultTtl);

        return $result;
    }

    private function connectionName(): string
    {
        $connection = $this->executor->connection();

        return method_exists($connection, 'name') ? (string) $connection->name() : 'default';
    }

    public function first(): mixed
    {
        $query = clone $this->query;
        $query->limit(1);

        $row = $this->timedOne($query, $this->parameters);

        if ($row === null) {
            return null;
        }

        if ($this->castApplier !== null) {
            $row = $this->castApplier->applyRow($row);
        }

        return $this->hydrator->hydrate($row);
    }

    public function value(string $column): mixed
    {
        $query = clone $this->query;
        $query->select($column);

        $value = $this->timedValue($query, $this->parameters);

        return $value === false ? null : $value;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $records = $this->get();
        $values = [];

        foreach ($records as $record) {
            $value = self::readAttribute($record, $column);
            if ($key === null) {
                $values[] = $value;

                continue;
            }

            $values[(string) self::readAttribute($record, $key)] = $value;
        }

        return $values;
    }

    public function count(): int
    {
        $inner = clone $this->query;
        $inner->clearOrderings()->clearLimitOffset();

        $simple = ! $inner->isDistinct()
            && $inner->groups() === []
            && $inner->havingExpression() === null
            && $inner->unions() === []
            && $inner->ctes() === []
            && $inner->fromSubquery() === null;

        if ($simple) {
            // Fast path: COUNT(*) directly, no derived table wrapper.
            $inner->select(new Aggregate(AggregateFunction::Count, '*'));

            return (int) $this->timedValue($inner, $this->parameters);
        }

        $wrapped = SelectQuery::make($inner, 'ude_count')
            ->select(new Aggregate(AggregateFunction::Count, '*'));

        return (int) $this->timedValue($wrapped, $this->parameters);
    }

    public function exists(): bool
    {
        if ($this->capabilities()->supports(Capability::EXISTS_PROJECTION)) {
            return $this->timedExists($this->query, $this->parameters);
        }

        $inner = clone $this->query;
        $inner->clearOrderings()->limit(1);

        $wrapped = SelectQuery::make($inner, 'ude_exists_check')
            ->select(new Aggregate(AggregateFunction::Count, RawExpression::trusted('*')));

        return (int) $this->timedValue($wrapped, $this->parameters) > 0;
    }

    /**
     * Defer this read until the request boundary. Duplicate deferred queries
     * (same AST and parameters) are resolved once and share the first row.
     */
    public function defer(): DeferredResult
    {
        return $this->executor->deferred()->defer(clone $this->query, $this->parameters);
    }

    public function recordset(): RecordsetInterface
    {
        return new ArrayRecordset($this->get());
    }

    public function rawRecordset(): RecordsetInterface
    {
        return new ArrayRecordset($this->timedRows($this->query, $this->parameters));
    }

    public function cursor(): RecordsetInterface
    {
        return new StreamingRecordset(
            fn (): \Traversable => $this->timedCursor($this->query, $this->parameters),
            $this->hydrator,
        );
    }

    /**
     * @return \Generator<int, mixed>
     */
    public function lazy(int $chunkSize = 1000): \Generator
    {
        $index = 0;
        foreach ($this->cursor() as $record) {
            yield $index++ => $record;
        }
    }

    public function chunk(int $size, callable $callback): bool
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        $page = 1;
        $buffer = [];

        foreach ($this->cursor() as $record) {
            $buffer[] = $record;

            if (count($buffer) === $size) {
                $result = $callback($buffer, $page++);

                if ($result === false) {
                    return false;
                }

                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $result = $callback($buffer, $page);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function paginate(int $perPage = 15, int $page = 1): PaginatorInterface
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $total = $this->count();
        $records = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return new Paginator($records, $total, $perPage, $page);
    }

    public function simplePaginate(int $perPage = 15, int $page = 1): PaginatorInterface
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $records = $this->limit($perPage + 1)->offset(($page - 1) * $perPage)->get();
        $hasMore = count($records) > $perPage;

        return new Paginator(array_slice($records, 0, $perPage), null, $perPage, $page);
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insert(array $rows): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('INSERT requires a table.');
        }

        $insert = InsertQuery::into($table);
        foreach ($rows as $row) {
            $insert->values($row);
        }

        return $this->timedExecute($insert, $this->parameters);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>|null  $updateColumns
     */
    public function upsert(array $rows, array $uniqueBy, ?array $updateColumns = null): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('UPSERT requires a table.');
        }

        $insert = InsertQuery::into($table)->onConflict($uniqueBy)->upsert($updateColumns);
        foreach ($rows as $row) {
            $insert->values($row);
        }

        return $this->timedExecute($insert, $this->parameters);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('UPDATE requires a table.');
        }

        $update = UpdateQuery::make($table)->set($values);

        if (($where = $this->query->whereExpression()) !== null) {
            $update->where($where);
        }

        if (($limit = $this->query->limitValue()) !== null) {
            $update->limit($limit);
        }

        $this->destructivePolicy()->assertAllowed(
            'UPDATE '.$table->name,
            $update->whereExpression() !== null,
            $this->confirmedDestructive,
        );

        return $this->timedExecute($update, $this->parameters);
    }

    public function delete(): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('DELETE requires a table.');
        }

        $delete = DeleteQuery::from($table);

        if (($where = $this->query->whereExpression()) !== null) {
            $delete->where($where);
        }

        if (($limit = $this->query->limitValue()) !== null) {
            $delete->limit($limit);
        }

        $this->destructivePolicy()->assertAllowed(
            'DELETE '.$table->name,
            $delete->whereExpression() !== null,
            $this->confirmedDestructive,
        );

        return $this->timedExecute($delete, $this->parameters);
    }

    // ------------------------------------------------------------------
    // Bulk operations (batch planner aware)
    // ------------------------------------------------------------------

    /**
     * Bulk insert split by the dialect placeholder budget. No model events and no
     * hydration are involved (raw rows only); documented and intentional.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $planner = new BatchPlanner($this->dialect());
        $columns = max(1, count((array) reset($rows)));

        $total = 0;
        foreach ($planner->chunkRowsAdaptive($rows, $columns) as $chunk) {
            $total += $this->insert($chunk);
        }

        return $total;
    }

    /**
     * Eloquent-style `createMany`: raw-row bulk insert (no events, no
     * hydration); returns the affected row count, not model instances.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function createMany(array $rows): int
    {
        return $this->insertMany($rows);
    }

    /**
     * `updateOrCreate` in bulk: upsert per unique key. Rows must contain every
     * column (Laravel `upsert` semantics); for partial rows use the single-row
     * `updateOrCreate()`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>|null  $updateColumns
     */
    public function updateOrCreateMany(array $rows, array $uniqueBy, ?array $updateColumns = null): int
    {
        return $this->upsertMany($rows, $uniqueBy, $updateColumns);
    }

    /**
     * Bulk `insertOrIgnore` split by the dialect placeholder budget.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertOrIgnoreMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $planner = new BatchPlanner($this->dialect());
        $columns = max(1, count((array) reset($rows)));

        $total = 0;
        foreach ($planner->chunkRowsAdaptive($rows, $columns) as $chunk) {
            $total += $this->insertOrIgnore($chunk);
        }

        return $total;
    }

    /**
     * Universal SQL function call usable inside projections, orders and
     * comparisons: `->udf('coalesce', 'nickname', 'name')`.
     */
    public function udf(string $name, mixed ...$arguments): FunctionCall
    {
        return Expression::func($name, ...$arguments);
    }

    /**
     * Bulk upsert split by the dialect placeholder budget.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     * @param  list<string>|null  $updateColumns
     */
    public function upsertMany(array $rows, array $uniqueBy, ?array $updateColumns = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $planner = new BatchPlanner($this->dialect());
        $columns = max(1, count((array) reset($rows)));

        $total = 0;
        foreach ($planner->chunkRowsAdaptive($rows, $columns) as $chunk) {
            $total += $this->upsert($chunk, $uniqueBy, $updateColumns);
        }

        return $total;
    }

    /**
     * Bulk update by key list, chunked to respect placeholder limits.
     *
     * @param  array<string, mixed>  $values
     * @param  list<int|string>  $ids
     */
    public function updateMany(array $values, array $ids, string $key = 'id'): int
    {
        if ($ids === []) {
            return 0;
        }

        $planner = new BatchPlanner($this->dialect());
        $total = 0;

        foreach ($planner->chunkValues($ids) as $chunk) {
            $builder = self::table($this->executor, $this->requireTableName())
                ->using($this->parameters)
                ->usingPolicy($this->destructivePolicy())
                ->whereIn($key, $chunk);

            $total += $builder->update($values);
        }

        return $total;
    }

    /**
     * Bulk delete by key list, chunked to respect placeholder limits.
     *
     * @param  list<int|string>  $ids
     */
    public function deleteMany(array $ids, string $key = 'id'): int
    {
        if ($ids === []) {
            return 0;
        }

        $planner = new BatchPlanner($this->dialect());
        $total = 0;

        foreach ($planner->chunkValues($ids) as $chunk) {
            $builder = self::table($this->executor, $this->requireTableName())
                ->using($this->parameters)
                ->usingPolicy($this->destructivePolicy())
                ->whereIn($key, $chunk);

            $total += $builder->delete();
        }

        return $total;
    }

    public function batchPlanner(): BatchPlanner
    {
        return new BatchPlanner($this->dialect());
    }

    private function requireTableName(): string
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('The operation requires a table.');
        }

        return $table->name;
    }

    private function assertVectorSupport(): void
    {
        if ($this->capabilities()->supports(Capability::VECTOR)) {
            return;
        }

        throw new UnsupportedFeatureException(
            feature: 'VECTOR DISTANCE',
            engine: $this->dialect()->vendor()->value,
            engineVersion: $this->dialect()->version()->raw,
            reason: 'Vector distance requires MariaDB 11.7+ or PostgreSQL with pgvector',
            alternatives: ['Filter in the application layer', 'Use dedicated search infrastructure (e.g. vector database)'],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timedRows(QueryObjectInterface $query, array $parameters = []): array
    {
        return $this->executor->withStatementTimeout($this->timeoutSeconds, fn (): array => $this->executor->rows($query, $parameters));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function timedOne(QueryObjectInterface $query, array $parameters = []): ?array
    {
        return $this->executor->withStatementTimeout($this->timeoutSeconds, fn (): ?array => $this->executor->one($query, $parameters));
    }

    private function timedExists(SelectQuery $query, array $parameters = []): bool
    {
        return $this->executor->withStatementTimeout($this->timeoutSeconds, fn (): bool => $this->executor->exists($query, $parameters));
    }

    private function timedValue(QueryObjectInterface $query, array $parameters = []): mixed
    {
        return $this->executor->withStatementTimeout($this->timeoutSeconds, fn (): mixed => $this->executor->value($query, $parameters));
    }

    private function timedExecute(QueryObjectInterface $query, array $parameters = []): int
    {
        return $this->executor->withStatementTimeout($this->timeoutSeconds, fn (): int => $this->executor->execute($query, $parameters));
    }

    private function timedCursor(QueryObjectInterface $query, array $parameters = []): CursorInterface
    {
        return $this->executor->withStatementTimeout($this->timeoutSeconds, fn (): CursorInterface => $this->executor->cursor($query, $parameters));
    }

    // ------------------------------------------------------------------
    // Laravel 13 parity extensions
    // ------------------------------------------------------------------

    public function when(mixed $value, callable $callback, ?callable $default = null): self
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default !== null) {
            $default($this, $value);
        }

        return $this;
    }

    public function unless(mixed $value, callable $callback, ?callable $default = null): self
    {
        return $this->when(! $value, $callback, $default);
    }

    public function tap(callable $callback): self
    {
        $callback($this);

        return $this;
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * @param  list<mixed>  $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        $this->query->orWhere(RawExpression::parameterized($sql, $bindings));

        return $this;
    }

    public function orWhereColumn(string $first, string $operator, string $second): self
    {
        $this->query->orWhere(Comparison::of(Column::make($first), $operator, Column::make($second)));

        return $this;
    }

    public function orWhereNot(string $column, mixed $value): self
    {
        $this->query->orWhere(Column::make($column)->neq($value));

        return $this;
    }

    public function orWhereNotBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->orWhere(Column::make($column)->notBetween($low, $high));

        return $this;
    }

    public function orWhereExists(Closure|QueryBuilder|SelectQuery $query): self
    {
        if ($query instanceof Closure) {
            $sub = new self($this->executor, SelectQuery::make());
            $query($sub);

            $this->query->orWhere(new Exists($sub->query()));

            return $this;
        }

        $this->query->orWhere(new Exists($query instanceof QueryBuilder ? $query->query() : $query));

        return $this;
    }

    public function orWhereNotExists(Closure|QueryBuilder|SelectQuery $query): self
    {
        if ($query instanceof Closure) {
            $sub = new self($this->executor, SelectQuery::make());
            $query($sub);

            $this->query->orWhere(new Exists($sub->query(), true));

            return $this;
        }

        $this->query->orWhere(new Exists($query instanceof QueryBuilder ? $query->query() : $query, true));

        return $this;
    }

    public function whereLike(string $column, string $value, bool $caseSensitive = false): self
    {
        $this->query->where(new Comparison(Column::make($column), self::likeOperator($caseSensitive, false), Expression::normalizeOperand($value)));

        return $this;
    }

    public function orWhereLike(string $column, string $value, bool $caseSensitive = false): self
    {
        $this->query->orWhere(new Comparison(Column::make($column), self::likeOperator($caseSensitive, false), Expression::normalizeOperand($value)));

        return $this;
    }

    public function whereNotLike(string $column, string $value, bool $caseSensitive = false): self
    {
        $this->query->where(new Comparison(Column::make($column), self::likeOperator($caseSensitive, true), Expression::normalizeOperand($value)));

        return $this;
    }

    public function orWhereNotLike(string $column, string $value, bool $caseSensitive = false): self
    {
        $this->query->orWhere(new Comparison(Column::make($column), self::likeOperator($caseSensitive, true), Expression::normalizeOperand($value)));

        return $this;
    }

    public function whereNullSafeEquals(string $column, mixed $value): self
    {
        $this->query->where(Comparison::of(Column::make($column), 'is not distinct from', Expression::normalizeOperand($value)));

        return $this;
    }

    public function orWhereNullSafeEquals(string $column, mixed $value): self
    {
        $this->query->orWhere(Comparison::of(Column::make($column), 'is not distinct from', Expression::normalizeOperand($value)));

        return $this;
    }

    public function whereBinary(string $column, mixed $value): self
    {
        $this->query->where(new Comparison(Column::make($column), ComparisonOperator::BinaryEq, Expression::normalizeOperand($value)));

        return $this;
    }

    public function orWhereBinary(string $column, mixed $value): self
    {
        $this->query->orWhere(new Comparison(Column::make($column), ComparisonOperator::BinaryEq, Expression::normalizeOperand($value)));

        return $this;
    }

    public function whereNotBinary(string $column, mixed $value): self
    {
        $this->query->where(new Comparison(Column::make($column), ComparisonOperator::BinaryNeq, Expression::normalizeOperand($value)));

        return $this;
    }

    public function orWhereNotBinary(string $column, mixed $value): self
    {
        $this->query->orWhere(new Comparison(Column::make($column), ComparisonOperator::BinaryNeq, Expression::normalizeOperand($value)));

        return $this;
    }

    /**
     * @param  list<int|string>  $values
     */
    public function whereIntegerInRaw(string $column, array $values): self
    {
        $this->query->where(Column::make($column)->in(self::integerList($values)));

        return $this;
    }

    /**
     * @param  list<int|string>  $values
     */
    public function orWhereIntegerInRaw(string $column, array $values): self
    {
        $this->query->orWhere(Column::make($column)->in(self::integerList($values)));

        return $this;
    }

    /**
     * @param  list<int|string>  $values
     */
    public function whereIntegerNotInRaw(string $column, array $values): self
    {
        $this->query->where(Column::make($column)->notIn(self::integerList($values)));

        return $this;
    }

    /**
     * @param  list<int|string>  $values
     */
    public function orWhereIntegerNotInRaw(string $column, array $values): self
    {
        $this->query->orWhere(Column::make($column)->notIn(self::integerList($values)));

        return $this;
    }

    public function whereDate(string $column, string $operator, mixed $value): self
    {
        $this->query->where(Comparison::of(new DateExpression('date', Column::make($column)), $operator, Expression::normalizeOperand(self::dateValue($value))));

        return $this;
    }

    public function orWhereDate(string $column, string $operator, mixed $value): self
    {
        $this->query->orWhere(Comparison::of(new DateExpression('date', Column::make($column)), $operator, Expression::normalizeOperand(self::dateValue($value))));

        return $this;
    }

    public function whereTime(string $column, string $operator, mixed $value): self
    {
        $this->query->where(Comparison::of(new DateExpression('time', Column::make($column)), $operator, Expression::normalizeOperand(self::timeValue($value))));

        return $this;
    }

    public function orWhereTime(string $column, string $operator, mixed $value): self
    {
        $this->query->orWhere(Comparison::of(new DateExpression('time', Column::make($column)), $operator, Expression::normalizeOperand(self::timeValue($value))));

        return $this;
    }

    public function orWhereYear(string $column, int|string $value): self
    {
        $this->query->orWhere((new DateExpression('year', Column::make($column)))->eq($value));

        return $this;
    }

    public function orWhereMonth(string $column, int|string $value): self
    {
        $this->query->orWhere((new DateExpression('month', Column::make($column)))->eq($value));

        return $this;
    }

    public function orWhereDay(string $column, int|string $value): self
    {
        $this->query->orWhere((new DateExpression('day', Column::make($column)))->eq($value));

        return $this;
    }

    /**
     * Relative date sugar. `$now` defaults to PHP's current time; pass an app
     * timezone instant when the application clock differs.
     */
    public function whereToday(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '=', self::today($now));
    }

    public function orWhereToday(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '=', self::today($now));
    }

    public function whereTodayOrAfter(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '>=', self::today($now));
    }

    public function orWhereTodayOrAfter(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '>=', self::today($now));
    }

    public function whereTodayOrBefore(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '<=', self::today($now));
    }

    public function orWhereTodayOrBefore(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '<=', self::today($now));
    }

    public function whereBeforeToday(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '<', self::today($now));
    }

    public function orWhereBeforeToday(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '<', self::today($now));
    }

    public function whereAfterToday(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '>', self::today($now));
    }

    public function orWhereAfterToday(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '>', self::today($now));
    }

    public function wherePast(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '<', self::today($now));
    }

    public function orWherePast(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '<', self::today($now));
    }

    public function whereFuture(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '>', self::today($now));
    }

    public function orWhereFuture(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '>', self::today($now));
    }

    public function whereNowOrPast(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '<=', self::today($now));
    }

    public function orWhereNowOrPast(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '<=', self::today($now));
    }

    public function whereNowOrFuture(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->whereDate($column, '>=', self::today($now));
    }

    public function orWhereNowOrFuture(string $column, ?DateTimeInterface $now = null): self
    {
        return $this->orWhereDate($column, '>=', self::today($now));
    }

    /**
     * @param  list<string>  $columns
     */
    public function whereAll(array $columns, mixed $operator = null, mixed $value = null): self
    {
        [$value, $operator] = self::prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $this->query->where(function (PredicateGroup $group) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $group->where($column, $operator, $value);
            }
        });

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function orWhereAll(array $columns, mixed $operator = null, mixed $value = null): self
    {
        [$value, $operator] = self::prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $this->query->orWhere(function (PredicateGroup $group) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $group->where($column, $operator, $value);
            }
        });

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function whereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        [$value, $operator] = self::prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $this->query->where(function (PredicateGroup $group) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $group->orWhere($column, $operator, $value);
            }
        });

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function orWhereAny(array $columns, mixed $operator = null, mixed $value = null): self
    {
        [$value, $operator] = self::prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $this->query->orWhere(function (PredicateGroup $group) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $group->orWhere($column, $operator, $value);
            }
        });

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function whereNone(array $columns, mixed $operator = null, mixed $value = null): self
    {
        [$value, $operator] = self::prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $this->query->where(function (PredicateGroup $group) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $group->where($column, $operator === '=' ? '!=' : $operator, $value);
            }
        });

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function orWhereNone(array $columns, mixed $operator = null, mixed $value = null): self
    {
        [$value, $operator] = self::prepareValueAndOperator($value, $operator, func_num_args() === 2);

        $this->query->orWhere(function (PredicateGroup $group) use ($columns, $operator, $value): void {
            foreach ($columns as $column) {
                $group->where($column, $operator === '=' ? '!=' : $operator, $value);
            }
        });

        return $this;
    }

    /**
     * @param  list<string>  $values  [minColumn, maxColumn]
     */
    public function whereBetweenColumns(string $column, array $values): self
    {
        [$low, $high] = self::columnPair($values);

        $this->query->where(new Between(Column::make($column), Column::make($low), Column::make($high)));

        return $this;
    }

    /**
     * @param  list<string>  $values  [minColumn, maxColumn]
     */
    public function orWhereBetweenColumns(string $column, array $values): self
    {
        [$low, $high] = self::columnPair($values);

        $this->query->orWhere(new Between(Column::make($column), Column::make($low), Column::make($high)));

        return $this;
    }

    /**
     * @param  list<string>  $values  [minColumn, maxColumn]
     */
    public function whereNotBetweenColumns(string $column, array $values): self
    {
        [$low, $high] = self::columnPair($values);

        $this->query->where(new Between(Column::make($column), Column::make($low), Column::make($high), true));

        return $this;
    }

    /**
     * @param  list<string>  $values  [minColumn, maxColumn]
     */
    public function orWhereNotBetweenColumns(string $column, array $values): self
    {
        [$low, $high] = self::columnPair($values);

        $this->query->orWhere(new Between(Column::make($column), Column::make($low), Column::make($high), true));

        return $this;
    }

    /**
     * @param  list<string>  $columns  [minColumn, maxColumn]
     */
    public function whereValueBetween(mixed $value, array $columns): self
    {
        [$low, $high] = self::columnPair($columns);

        $this->query->where(new Between(Expression::normalizeOperand($value), Column::make($low), Column::make($high)));

        return $this;
    }

    /**
     * @param  list<string>  $columns  [minColumn, maxColumn]
     */
    public function orWhereValueBetween(mixed $value, array $columns): self
    {
        [$low, $high] = self::columnPair($columns);

        $this->query->orWhere(new Between(Expression::normalizeOperand($value), Column::make($low), Column::make($high)));

        return $this;
    }

    /**
     * @param  list<string>  $columns  [minColumn, maxColumn]
     */
    public function whereValueNotBetween(mixed $value, array $columns): self
    {
        [$low, $high] = self::columnPair($columns);

        $this->query->where(new Between(Expression::normalizeOperand($value), Column::make($low), Column::make($high), true));

        return $this;
    }

    /**
     * @param  list<string>  $columns  [minColumn, maxColumn]
     */
    public function orWhereValueNotBetween(mixed $value, array $columns): self
    {
        [$low, $high] = self::columnPair($columns);

        $this->query->orWhere(new Between(Expression::normalizeOperand($value), Column::make($low), Column::make($high), true));

        return $this;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<mixed>  $values
     */
    public function whereRowValues(array $columns, string $operator, array $values): self
    {
        $this->query->where(self::rowValues($columns, $operator, $values));

        return $this;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<mixed>  $values
     */
    public function orWhereRowValues(array $columns, string $operator, array $values): self
    {
        $this->query->orWhere(self::rowValues($columns, $operator, $values));

        return $this;
    }

    public function whereJsonContainsKey(string $column, string $key): self
    {
        $this->query->where(Expression::json(Column::make($column), '$')->containsKey($key));

        return $this;
    }

    public function orWhereJsonContainsKey(string $column, string $key): self
    {
        $this->query->orWhere(Expression::json(Column::make($column), '$')->containsKey($key));

        return $this;
    }

    public function whereJsonDoesntContain(string $column, mixed $value): self
    {
        $this->query->where(new LogicalExpression(LogicalOperator::Not, [Expression::json(Column::make($column), '$')->contains($value)]));

        return $this;
    }

    public function orWhereJsonDoesntContain(string $column, mixed $value): self
    {
        $this->query->orWhere(new LogicalExpression(LogicalOperator::Not, [Expression::json(Column::make($column), '$')->contains($value)]));

        return $this;
    }

    public function whereJsonDoesntContainKey(string $column, string $key): self
    {
        $this->query->where(new LogicalExpression(LogicalOperator::Not, [Expression::json(Column::make($column), '$')->containsKey($key)]));

        return $this;
    }

    public function orWhereJsonDoesntContainKey(string $column, string $key): self
    {
        $this->query->orWhere(new LogicalExpression(LogicalOperator::Not, [Expression::json(Column::make($column), '$')->containsKey($key)]));

        return $this;
    }

    public function orWhereJsonContains(string $column, mixed $value): self
    {
        $this->query->orWhere(Expression::json(Column::make($column), '$')->contains($value));

        return $this;
    }

    public function whereJsonLength(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        $this->query->where(Comparison::of(
            new JsonLengthExpression(Expression::json(Column::make($column), '$')),
            (string) $operator,
            Expression::normalizeOperand((int) $value),
        ));

        return $this;
    }

    public function orWhereJsonLength(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        $this->query->orWhere(Comparison::of(
            new JsonLengthExpression(Expression::json(Column::make($column), '$')),
            (string) $operator,
            Expression::normalizeOperand((int) $value),
        ));

        return $this;
    }

    public function whereJsonOverlaps(string $column, mixed $value): self
    {
        $this->query->where(Expression::json(Column::make($column), '$')->overlaps($value));

        return $this;
    }

    public function orWhereJsonOverlaps(string $column, mixed $value): self
    {
        $this->query->orWhere(Expression::json(Column::make($column), '$')->overlaps($value));

        return $this;
    }

    public function whereJsonDoesntOverlap(string $column, mixed $value): self
    {
        $this->query->where(new LogicalExpression(LogicalOperator::Not, [Expression::json(Column::make($column), '$')->overlaps($value)]));

        return $this;
    }

    public function orWhereJsonDoesntOverlap(string $column, mixed $value): self
    {
        $this->query->orWhere(new LogicalExpression(LogicalOperator::Not, [Expression::json(Column::make($column), '$')->overlaps($value)]));

        return $this;
    }

    /**
     * Order by the position of the value inside the given list. Portable CASE
     * expression; values outside the list go last.
     *
     * @param  list<mixed>  $values
     */
    public function inOrderOf(string $column, array $values): self
    {
        $case = CaseExpression::make();
        $index = 0;

        foreach ($values as $value) {
            $case = $case->when(
                Comparison::of(Column::make($column), '=', Expression::normalizeOperand($value)),
                Expression::normalizeOperand($index),
            );
            $index++;
        }

        $this->query->orderBy($case->else(Expression::normalizeOperand($index)), 'asc');

        return $this;
    }

    /**
     * Raw FROM source with explicit bindings.
     *
     * @param  list<mixed>  $bindings
     */
    public function fromRaw(string $sql, array $bindings = [], ?string $alias = null): self
    {
        $this->query->fromRaw($sql, $bindings, $alias);

        return $this;
    }

    /**
     * Limit rows per group with ROW_NUMBER() over a partition (Laravel 13
     * groupLimit). Offsets are not supported.
     *
     * @param  string|list<string>  $column
     */
    public function groupLimit(int $value, string|array $column): self
    {
        $value = max(1, $value);
        $columns = array_map(static fn (string $column): Column => Column::make($column), is_array($column) ? array_values($column) : [$column]);

        $inner = clone $this->query;

        if ($inner->offsetValue() !== null) {
            throw new \InvalidArgumentException('groupLimit() does not support OFFSET; remove the offset first.');
        }

        $orders = $inner->orders();
        $inner->clearOrderings();

        $window = new WindowExpression(new FunctionCall('row_number'), null, $columns, $orders);
        $projection = $inner->columns() === [] ? [RawExpression::trusted('*')] : $inner->columns();
        $inner->select(...[...$projection, $window->as('laravel_row')]);

        $outer = SelectQuery::make()
            ->from($inner, 'laravel_table')
            ->where(Column::make('laravel_row')->lte($value))
            ->orderBy('laravel_row', 'asc');

        $this->query = $outer;

        return $this;
    }

    public function useIndex(string $index): self
    {
        $this->query->indexHint('use', $this->validIndexName($index));

        return $this;
    }

    public function forceIndex(string $index): self
    {
        $this->query->indexHint('force', $this->validIndexName($index));

        return $this;
    }

    public function ignoreIndex(string $index): self
    {
        $this->query->indexHint('ignore', $this->validIndexName($index));

        return $this;
    }

    private function validIndexName(string $index): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $index) !== 1) {
            throw new \InvalidArgumentException("Unsupported index name [{$index}].");
        }

        return $index;
    }

    /**
     * Full-text predicate. MySQL/MariaDB compile MATCH ... AGAINST; PostgreSQL
     * compiles to_tsvector @@ tsquery; the rest throw with alternatives.
     *
     * @param  string|list<string>  $columns
     * @param  array{mode?: string|null, expanded?: bool, language?: string|null, vector?: bool}  $options
     */
    public function whereFullText(string|array $columns, string $value, array $options = []): self
    {
        $this->query->where(new FullText($this->fullTextColumns($columns), Expression::normalizeOperand($value), $options));

        return $this;
    }

    /**
     * @param  string|list<string>  $columns
     * @param  array{mode?: string|null, expanded?: bool, language?: string|null, vector?: bool}  $options
     */
    public function orWhereFullText(string|array $columns, string $value, array $options = []): self
    {
        $this->query->orWhere(new FullText($this->fullTextColumns($columns), Expression::normalizeOperand($value), $options));

        return $this;
    }

    /**
     * UPDATE ... FROM (PostgreSQL). Values are bound; tables are validated identifiers.
     *
     * @param  array<string, mixed>  $values
     * @param  string|list<string>  $from
     */
    public function updateFrom(array $values, string|array $from): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('UPDATE requires a table.');
        }

        $tables = is_array($from) ? array_values($from) : [$from];

        if ($tables === []) {
            throw new \InvalidArgumentException('updateFrom() requires at least one FROM table.');
        }

        foreach ($tables as $fromTable) {
            if (! is_string($fromTable) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $fromTable) !== 1) {
                throw new \InvalidArgumentException("Unsupported FROM table [{$fromTable}].");
            }
        }

        $update = UpdateQuery::make($table)->set($values)->from(...$tables);

        if (($where = $this->query->whereExpression()) !== null) {
            $update->where($where);
        }

        if (($limit = $this->query->limitValue()) !== null) {
            $update->limit($limit);
        }

        $this->destructivePolicy()->assertAllowed(
            'UPDATE '.$table->name,
            $update->whereExpression() !== null,
            $this->confirmedDestructive,
        );

        return $this->timedExecute($update, $this->parameters);
    }

    /**
     * @param  string|list<string>  $columns
     * @return list<string>
     */
    private function fullTextColumns(string|array $columns): array
    {
        $validated = [];

        foreach (is_array($columns) ? array_values($columns) : [$columns] as $column) {
            if (! is_string($column) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column) !== 1) {
                throw new \InvalidArgumentException("Unsupported full-text column [{$column}].");
            }

            $validated[] = $column;
        }

        if ($validated === []) {
            throw new \InvalidArgumentException('whereFullText() requires at least one column.');
        }

        return $validated;
    }

    public function havingNull(string $column): self
    {
        $this->query->having(Column::make($column)->isNull());

        return $this;
    }

    public function orHavingNull(string $column): self
    {
        $this->query->orHaving(Column::make($column)->isNull());

        return $this;
    }

    public function havingNotNull(string $column): self
    {
        $this->query->having(Column::make($column)->isNotNull());

        return $this;
    }

    public function orHavingNotNull(string $column): self
    {
        $this->query->orHaving(Column::make($column)->isNotNull());

        return $this;
    }

    public function havingBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->having(Column::make($column)->between($low, $high));

        return $this;
    }

    public function orHavingBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->orHaving(Column::make($column)->between($low, $high));

        return $this;
    }

    public function havingNotBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->having(Column::make($column)->notBetween($low, $high));

        return $this;
    }

    public function orHavingNotBetween(string $column, mixed $low, mixed $high): self
    {
        $this->query->orHaving(Column::make($column)->notBetween($low, $high));

        return $this;
    }

    /**
     * @param  list<mixed>  $bindings
     */
    public function orHavingRaw(string $sql, array $bindings = []): self
    {
        $this->query->orHaving(RawExpression::parameterized($sql, $bindings));

        return $this;
    }

    public function reorder(string|Expression|null $column = null, string $direction = 'asc'): self
    {
        $this->query->clearOrderings();

        if ($column !== null) {
            $this->query->orderBy($column, $direction);
        }

        return $this;
    }

    public function reorderDesc(string|Expression $column): self
    {
        return $this->reorder($column, 'desc');
    }

    public function inRandomOrder(): self
    {
        $this->query->orderBy(RawExpression::trusted($this->dialect()->randomFunction()), 'asc');

        return $this;
    }

    public function forPage(int $page, int $perPage = 15): self
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function forPageAfterId(int $perPage = 15, int|string $lastId = 0, string $column = 'id'): self
    {
        $this->query->clearOrderings();

        if ($lastId !== 0 && $lastId !== '0') {
            $this->query->where(Column::make($column)->gt($lastId));
        }

        $this->query->orderBy($column);

        return $this->limit($perPage);
    }

    public function forPageBeforeId(int $perPage = 15, int|string $lastId = 0, string $column = 'id'): self
    {
        $this->query->clearOrderings();

        if ($lastId !== 0 && $lastId !== '0') {
            $this->query->where(Column::make($column)->lt($lastId));
        }

        $this->query->orderByDesc($column);

        return $this->limit($perPage);
    }

    public function find(int|string $id, array|string $columns = ['*']): mixed
    {
        $builder = clone $this;
        $builder->query->where(Column::make('id')->eq($id));

        if ($columns !== ['*'] && $columns !== '*') {
            $builder->select(...((array) $columns));
        }

        return $builder->first();
    }

    public function findOr(int|string $id, array|string $columns = ['*'], mixed $default = null): mixed
    {
        $record = $this->find($id, $columns);

        if ($record !== null) {
            return $record;
        }

        return is_callable($default) ? $default() : $default;
    }

    public function soleValue(string $column): mixed
    {
        return self::readAttribute($this->sole(), $column);
    }

    public function firstOrFail(): mixed
    {
        $record = $this->first();

        if ($record === null) {
            throw new QueryException('No records found for firstOrFail().');
        }

        return $record;
    }

    public function raw(string $sql): RawExpression
    {
        return RawExpression::trusted($sql);
    }

    public function truncate(): void
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('TRUNCATE requires a table.');
        }

        $this->destructivePolicy()->assertAllowed('TRUNCATE '.$table->name, false, $this->confirmedDestructive);

        foreach ($this->dialect()->truncateStatements($table->name, $this->dialect()->quoteIdentifier($table->qualifiedName())) as $statement) {
            $this->timedExecute(new RawQuery($statement['sql'], $statement['bindings']));
        }
    }

    public function each(callable $callback, int $count = 1000): bool
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        $page = 1;

        while (true) {
            $results = (clone $this)->forPage($page, $count)->get();

            if ($results === []) {
                return true;
            }

            foreach ($results as $key => $result) {
                if ($callback($result, $key) === false) {
                    return false;
                }
            }

            if (count($results) < $count) {
                return true;
            }

            $page++;
        }
    }

    /**
     * @return list<mixed>
     */
    public function chunkMap(callable $callback, int $count = 1000): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        $results = [];
        $page = 1;

        while (true) {
            $chunk = (clone $this)->forPage($page, $count)->get();

            if ($chunk === []) {
                return $results;
            }

            $mapped = $callback($chunk, $page);

            if ($mapped === false) {
                return $results;
            }

            if (is_iterable($mapped)) {
                foreach ($mapped as $item) {
                    $results[] = $item;
                }
            } else {
                $results[] = $mapped;
            }

            if (count($chunk) < $count) {
                return $results;
            }

            $page++;
        }
    }

    public function chunkByIdDesc(int $size, callable $callback, string $column = 'id'): bool
    {
        $page = 1;
        $last = null;

        while (true) {
            $builder = self::table($this->executor, $this->requireTableName())
                ->using($this->parameters)
                ->orderByDesc($column);

            if ($last !== null) {
                $builder->where($column, '<', $last);
            }

            $chunk = $builder->limit($size)->get();

            if ($chunk === []) {
                return true;
            }

            if ($callback($chunk, $page++) === false) {
                return false;
            }

            $last = self::readAttribute($chunk[count($chunk) - 1], $column);

            if (count($chunk) < $size) {
                return true;
            }
        }
    }

    /**
     * @return \Generator<int, mixed>
     */
    public function lazyByIdDesc(int $size = 1000, string $column = 'id'): \Generator
    {
        $last = null;

        while (true) {
            $builder = self::table($this->executor, $this->requireTableName())
                ->using($this->parameters)
                ->orderByDesc($column);

            if ($last !== null) {
                $builder->where($column, '<', $last);
            }

            $chunk = $builder->limit($size)->get();

            if ($chunk === []) {
                return;
            }

            foreach ($chunk as $row) {
                yield $row;
            }

            $last = self::readAttribute($chunk[count($chunk) - 1], $column);

            if (count($chunk) < $size) {
                return;
            }
        }
    }

    public function orderedChunkById(int $size, callable $callback, string $column = 'id', bool $descending = false): bool
    {
        return $descending
            ? $this->chunkByIdDesc($size, $callback, $column)
            : $this->chunkById($size, $callback, $column);
    }

    /**
     * @param  array<string, int|float>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function incrementEach(array $columns, array $extra = []): int
    {
        $total = 0;

        foreach ($columns as $column => $amount) {
            $total += $this->increment($column, $amount, $extra);
        }

        return $total;
    }

    /**
     * @param  array<string, int|float>  $columns
     * @param  array<string, mixed>  $extra
     */
    public function decrementEach(array $columns, array $extra = []): int
    {
        $total = 0;

        foreach ($columns as $column => $amount) {
            $total += $this->decrement($column, $amount, $extra);
        }

        return $total;
    }

    public function crossJoinSub(QueryBuilder|SelectQuery $query, string $alias): self
    {
        $this->query->crossJoinSub($query instanceof QueryBuilder ? $query->query() : $query, $alias);

        return $this;
    }

    public function joinWhere(string $table, string $first, string $operator, mixed $second): self
    {
        $this->query->join($table, function (PredicateGroup $group) use ($first, $operator, $second): void {
            $group->where($first, $operator, $second);
        });

        return $this;
    }

    public function leftJoinWhere(string $table, string $first, string $operator, mixed $second): self
    {
        $this->query->leftJoin($table, function (PredicateGroup $group) use ($first, $operator, $second): void {
            $group->where($first, $operator, $second);
        });

        return $this;
    }

    public function rightJoinWhere(string $table, string $first, string $operator, mixed $second): self
    {
        $this->query->rightJoin($table, function (PredicateGroup $group) use ($first, $operator, $second): void {
            $group->where($first, $operator, $second);
        });

        return $this;
    }

    public function straightJoin(string $table, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->straightJoin($table, $first, $operator, $value);

        return $this;
    }

    public function straightJoinSub(QueryBuilder|SelectQuery $query, string $alias, Expression|Closure|string|null $first = null, ?string $operator = null, mixed $value = null): self
    {
        $this->query->straightJoinSub($query instanceof QueryBuilder ? $query->query() : $query, $alias, $first, $operator, $value);

        return $this;
    }

    public function straightJoinWhere(string $table, string $first, string $operator, mixed $second): self
    {
        $this->query->straightJoin($table, function (PredicateGroup $group) use ($first, $operator, $second): void {
            $group->where($first, $operator, $second);
        });

        return $this;
    }

    /**
     * Per-query statement timeout. Applied on MySQL/MariaDB (session
     * max_execution_time/max_statement_time) and PostgreSQL (statement_timeout);
     * elsewhere it compiles with a warning and no session change.
     */
    public function timeout(?int $seconds): self
    {
        if ($seconds !== null && $seconds <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than zero.');
        }

        $this->timeoutSeconds = $seconds;

        return $this;
    }

    /**
     * Vector distance lower than a maximum (cosine distance). Requires MariaDB
     * 11.7+ or PostgreSQL with pgvector.
     *
     * @param  array<int, float|int>|string|\Traversable<int, float|int>  $vector
     */
    public function whereVectorDistanceLessThan(string $column, array|string|\Traversable $vector, float $maxDistance, bool $or = false): self
    {
        $this->assertVectorSupport();

        $comparison = Comparison::of(
            VectorDistance::of($column, $vector),
            '<',
            Expression::normalizeOperand($maxDistance),
        );

        $or ? $this->query->orWhere($comparison) : $this->query->where($comparison);

        return $this;
    }

    /**
     * @param  array<int, float|int>|string|\Traversable<int, float|int>  $vector
     */
    public function orWhereVectorDistanceLessThan(string $column, array|string|\Traversable $vector, float $maxDistance): self
    {
        return $this->whereVectorDistanceLessThan($column, $vector, $maxDistance, true);
    }

    /**
     * Similarity search: distance <= 1 - minSimilarity, optionally ordered by
     * distance ascending.
     *
     * @param  array<int, float|int>|string|\Traversable<int, float|int>  $vector
     */
    public function whereVectorSimilarTo(string $column, array|string|\Traversable $vector, float $minSimilarity = 0.6, bool $order = true): self
    {
        $this->whereVectorDistanceLessThan($column, $vector, 1 - $minSimilarity);

        if ($order) {
            $this->orderByVectorDistance($column, $vector);
        }

        return $this;
    }

    /**
     * @param  array<int, float|int>|string|\Traversable<int, float|int>  $vector
     */
    public function selectVectorDistance(string $column, array|string|\Traversable $vector, ?string $alias = null): self
    {
        $this->assertVectorSupport();

        $alias ??= str_replace('.', '_', $column).'_distance';

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
            throw new \InvalidArgumentException("Unsupported distance alias [{$alias}].");
        }

        $this->query->addSelect(VectorDistance::of($column, $vector)->as($alias));

        return $this;
    }

    /**
     * @param  array<int, float|int>|string|\Traversable<int, float|int>  $vector
     */
    public function orderByVectorDistance(string $column, array|string|\Traversable $vector): self
    {
        $this->assertVectorSupport();

        $this->query->orderBy(VectorDistance::of($column, $vector), 'asc');

        return $this;
    }

    /**
     * INSERT IGNORE with RETURNING rows. Native on engines with RETURNING/OUTPUT;
     * on MySQL it requires $uniqueBy and falls back to insert + select.
     *
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $rows
     * @param  list<string>  $returning
     * @param  list<string>|string|null  $uniqueBy
     * @return list<array<string, mixed>>
     */
    public function insertOrIgnoreReturning(array $rows, array $returning = ['*'], array|string|null $uniqueBy = null): array
    {
        if ($rows === []) {
            return [];
        }

        if (! is_array(reset($rows))) {
            $rows = [$rows];
        }

        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('INSERT requires a table.');
        }

        if ($returning === []) {
            throw new \InvalidArgumentException('The returning columns must not be empty.');
        }

        if ($uniqueBy === [] || $uniqueBy === '') {
            throw new \InvalidArgumentException('The unique columns must not be empty.');
        }

        $insert = InsertQuery::into($table)->ignore()->returning($returning);
        foreach ($rows as $row) {
            $insert->values($row);
        }

        if ($this->capabilities()->supports(Capability::RETURNING) || $this->capabilities()->supports(Capability::OUTPUT_CLAUSE)) {
            return $this->timedRows($insert);
        }

        if ($uniqueBy === null) {
            throw new UnsupportedFeatureException(
                feature: 'INSERT OR IGNORE RETURNING',
                engine: $this->dialect()->vendor()->value,
                engineVersion: $this->dialect()->version()->raw,
                reason: 'The engine has no RETURNING clause; pass $uniqueBy to select the inserted rows',
                alternatives: ['Provide uniqueBy columns', 'Insert and query manually inside a transaction'],
            );
        }

        $this->insertOrIgnore($rows);

        $keys = is_array($uniqueBy) ? array_values($uniqueBy) : [$uniqueBy];
        $builder = self::table($this->executor, $this->requireTableName())->using($this->parameters);

        foreach (array_values($rows) as $index => $row) {
            $values = array_intersect_key($row, array_flip($keys));

            if ($values === []) {
                continue;
            }

            $index === 0
                ? $builder->where(function (PredicateGroup $group) use ($values): void {
                    foreach ($values as $column => $value) {
                        $group->where((string) $column, $value);
                    }
                })
                : $builder->orWhere(function (PredicateGroup $group) use ($values): void {
                    foreach ($values as $column => $value) {
                        $group->where((string) $column, $value);
                    }
                });
        }

        return $builder->select(...($returning === ['*'] ? ['*'] : $returning))->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertOrIgnore(array $rows): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('INSERT requires a table.');
        }

        $insert = InsertQuery::into($table)->ignore();
        foreach ($rows as $row) {
            $insert->values($row);
        }

        return $this->timedExecute($insert, $this->parameters);
    }

    /**
     * @param  list<string>  $columns
     */
    public function insertUsing(array $columns, QueryBuilder|SelectQuery $query): int
    {
        return $this->insertSelect($columns, $query, false);
    }

    /**
     * @param  list<string>  $columns
     */
    public function insertOrIgnoreUsing(array $columns, QueryBuilder|SelectQuery $query): int
    {
        return $this->insertSelect($columns, $query, true);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        $this->insert([$values]);

        return $this->executor->connection()->lastInsertId($sequence);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|callable  $values
     */
    public function updateOrInsert(array $attributes, array|callable $values = []): bool
    {
        $builder = clone $this;
        $builder->applyAttributeWheres($attributes);

        $resolved = is_callable($values) ? $values() : $values;

        if ($builder->exists()) {
            if ($resolved !== []) {
                $update = clone $builder;
                $update->update($resolved);
            }

            return true;
        }

        $this->insert([array_merge($attributes, $resolved)]);

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function insertSelect(array $columns, QueryBuilder|SelectQuery $query, bool $ignore): int
    {
        $table = $this->query->table();

        if ($table === null) {
            throw new QueryException('INSERT requires a table.');
        }

        $insert = InsertQuery::into($table)->fromSelect($query instanceof QueryBuilder ? $query->query() : $query);

        if ($columns !== []) {
            $insert->intoColumns($columns);
        }

        if ($ignore) {
            $insert->ignore();
        }

        return $this->timedExecute($insert, $this->parameters);
    }

    private static function likeOperator(bool $caseSensitive, bool $negated): ComparisonOperator
    {
        if ($caseSensitive) {
            return $negated ? ComparisonOperator::NotLikeBinary : ComparisonOperator::LikeBinary;
        }

        return $negated ? ComparisonOperator::NotLike : ComparisonOperator::Like;
    }

    /**
     * @param  list<int|string>  $values
     * @return list<int>
     */
    private static function integerList(array $values): array
    {
        $integers = [];

        foreach ($values as $value) {
            if (is_int($value)) {
                $integers[] = $value;

                continue;
            }

            if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                $integers[] = (int) $value;

                continue;
            }

            throw new \InvalidArgumentException('whereIntegerInRaw only accepts integers.');
        }

        return $integers;
    }

    private static function dateValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    private static function timeValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format('H:i:s') : $value;
    }

    private static function today(?DateTimeInterface $now): string
    {
        return ($now ?? new \DateTimeImmutable('now'))->format('Y-m-d');
    }

    /**
     * @return array{mixed, mixed}
     */
    private static function prepareValueAndOperator(mixed $value, mixed $operator, bool $useDefault): array
    {
        if ($useDefault) {
            return [$operator, '='];
        }

        return [$value, $operator];
    }

    /**
     * @param  list<string>  $values
     * @return array{string, string}
     */
    private static function columnPair(array $values): array
    {
        $values = array_values($values);

        if (count($values) !== 2) {
            throw new \InvalidArgumentException('Exactly two column names are required.');
        }

        return [(string) $values[0], (string) $values[1]];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<mixed>  $values
     */
    private static function rowValues(array $columns, string $operator, array $values): RowValues
    {
        $mapped = match (strtolower(trim($operator))) {
            '=', '==', 'eq' => ComparisonOperator::Eq,
            '!=', '<>', 'neq' => ComparisonOperator::Neq,
            '>', 'gt' => ComparisonOperator::Gt,
            '>=', 'gte' => ComparisonOperator::Gte,
            '<', 'lt' => ComparisonOperator::Lt,
            '<=', 'lte' => ComparisonOperator::Lte,
            default => throw new \InvalidArgumentException("Unsupported row values operator [{$operator}]."),
        };

        return new RowValues(
            array_map(static fn (string $column): Column => Column::make($column), array_values($columns)),
            $mapped,
            array_map(static fn (mixed $value): Expression => Expression::normalizeOperand($value), array_values($values)),
        );
    }

    // ------------------------------------------------------------------
    // Inspection
    // ------------------------------------------------------------------

    public function compile(): CompiledQuery
    {
        $compiled = $this->executor->compile($this->query);

        if ($this->timeoutSeconds !== null && ! $this->dialect()->supportsStatementTimeout()) {
            $compiled = $compiled->withWarnings([sprintf(
                'timeout(%d) ignored on %s %s: the engine has no per-query statement timeout.',
                $this->timeoutSeconds,
                $this->dialect()->vendor()->value,
                $this->dialect()->version()->raw,
            )]);
        }

        return $compiled;
    }

    public function sql(): string
    {
        return $this->compile()->sql;
    }

    /**
     * @return list<mixed>
     */
    public function bindings(): array
    {
        return $this->compile()->bindings;
    }

    public function toAst(): AstNodeInterface
    {
        return $this->query->toAst();
    }

    public function fingerprint(): QueryFingerprint
    {
        return $this->query->fingerprint();
    }

    /**
     * @return list<string>
     */
    public function requiredCapabilities(): array
    {
        return $this->query->requiredCapabilities();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPlan(): array
    {
        return [
            'ast' => $this->query->toAst()->nodeType(),
            'sql' => $this->sql(),
            'bindings' => count($this->bindings()),
            ...$this->query->toPlan(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queryPlan(): array
    {
        return $this->query->toPlan();
    }

    private static function readAttribute(mixed $record, string $attribute): mixed
    {
        if (is_array($record)) {
            return $record[$attribute] ?? null;
        }

        if ($record instanceof EntityInterface) {
            return $record->getAttribute($attribute);
        }

        if (is_object($record)) {
            return $record->{$attribute} ?? null;
        }

        return null;
    }
}
