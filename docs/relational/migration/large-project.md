# Migration: large project

Large project: several teams, many queries, different engines and performance
pressure. The key is to **standardize the engine, not just use it**.

## Governance

- A single `QueryExecutor` construction point per connection.
- Review rules: every new query goes through
  `portability()`/`performance()` before merging.
- `RawExpression::trusted()` with user input is forbidden
  ([Security](../security.md)).
- Golden SQL per dialect in each module's tests.

## Layer boundary

```php
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Builder\QueryBuilder;

final class QueryFactory
{
    public function __construct(
        private readonly QueryExecutor $executor,
    ) {}

    public function table(string $table): QueryBuilder
    {
        return QueryBuilder::table($this->executor, $table);
    }

    public function dialect(): AbstractDialect
    {
        return $this->executor->dialect();
    }
}
```

Domain services depend on `QueryFactory`, not on PDO. This way the engine is
tested with in-memory SQLite and in production uses the real engine.

## Quality gates

```php
$builder = $factory->table('orders')->select('id')->where('total', '>=', 100);

$plan = $builder->toPlan();              // structure + required capabilities
$port = $builder->portability();         // is it portable across engines?
$perf = $builder->performance();         // static complexity + hydration cost
```

Suggested threshold (team policy, not the package's): block merges with
`estimatedComplexity = high` without justification, or with `classification`
dialect-specific outside the target engine.

## Relations at scale

- Register all relations in `RelationRegistry` per module.
- Use `RelationOptimizer::plan()` to decide join/batch/chunked/subquery with
  data (number of parents, filters) before coding.
- Wire `N1Detector` with `Log` or `Telemetry` mode in non-production environments and
  `Exception` in CI ([Relations](../relations.md)).

## Schema and distributed migrations

```php
use OrmUniversal\Relational\Schema\SchemaDiffer;
use OrmUniversal\Relational\Schema\SchemaInspectorFactory;

$inspector = SchemaInspectorFactory::for($dialect, $executor);
$current = $inspector->snapshot();
$diff = (new SchemaDiffer)->diff($current, $desired);
```

The diff executes nothing; the DDL application is decided by the deployment
pipeline. See [Schema](../schema.md) and [Migrations](../migrations.md).

## Observability

`InMemoryProfiler` is useful in development; in production connect your PSR profiler
through `ProfilerInterface`. `summary()` groups repeated fingerprints, which usually reveals
N+1 and redundant queries ([Performance](../performance.md)).

## Bulk writes and maintenance

- `insertMany()`/`upsertMany()`/`updateMany()`/`deleteMany()` + `BatchPlanner`
  avoid exceeding placeholder limits.
- For large cleanups, prefer batches with `chunk()` and a transaction per batch.
- Document in each ADR whether a query is intentionally dialect-specific.

## Checklist

- [ ] A single executor construction path.
- [ ] Portability/performance gates in CI.
- [ ] Relations registered per module and N+1 telemetered.
- [ ] Schema diff reviewed before each release.
- [ ] Production profiler connected through the contract.
