# Optimizer

The optimization pipeline lives in `OrmUniversal\Relational\Optimizer` and is
**semantically safe**: no pass changes the query result.

## Documented order

```
Normalizer → Validator → Optimizer (passes) → PortabilityAnalyzer / PerformanceAnalyzer
```

## Normalizer

Canonicalizes boolean groups: flattens nested `AND`/`OR` of the same operator,
normalizes expressions and discards single-element wrappers. It works on
`SelectQuery` (`replaceWhere()`, `replaceHaving()`) or on a standalone expression.

```php
use OrmUniversal\Relational\Optimizer\Normalizer;

$normalized = (new Normalizer)->normalizeExpression($expression);
$node = (new Normalizer)->normalize($selectQuery);
```

## Validator

Structural validation that fails fast with `CompilationException` and does not mutate the
query:

- SELECT without a source (`table()` or `fromSubquery()`).
- INSERT without `values()` or `fromSelect()`.
- UPDATE without `SET`.
- Empty `LogicalExpression`.

`warnings(AstNodeInterface): list<string>` reports `UPDATE`/`DELETE` without WHERE
(not fatal; the destructive policy decides at execution time).

```php
use OrmUniversal\Relational\Optimizer\Validator;

(new Validator)->validate($selectQuery);
$warnings = (new Validator)->warnings($updateQuery);
```

## Optimizer and passes

```php
use OrmUniversal\Relational\Optimizer\Optimizer;

$optimizer = new Optimizer;              // default passes
$optimizer->addPass($miPass);            // extends the pipeline
$optimizer->passNames();
$optimizer->optimize($selectQuery);
$optimizer->optimizeExpression($expression);
```

Default passes (implementing `OptimizerPassInterface` with `name()` and
`apply(AstNodeInterface)`):

| Pass | `name()` | What it does |
|---|---|---|
| `Pass\DuplicatePredicatePass` | `duplicate-predicates` | removes duplicate predicates in AND/OR |
| `Pass\BooleanSimplificationPass` | `boolean-simplification` | `x and true ⇒ x`, `x or true ⇒ true`, empty group ⇒ `1 = 1` |
| `Pass\ConstantFoldingPass` | `constant-folding` | folds `Value` vs `Value` comparisons into `1 = 1`/`1 = 0` |

`Pass\ProjectionPruningPass` (`projection-pruning`) is also real: it removes
duplicate projections without dropping unique columns or touching aggregates. It is not
in the default pipeline; it is added with `addPass()`.

`ExpressionKey::of(Expression): string` is the structural key the
passes use to detect equivalences (column, value, operator, raw).

## PortabilityAnalyzer

Classifies each node as portable, partially portable or dialect-specific and
accumulates readable differences:

```php
use OrmUniversal\Relational\Optimizer\PortabilityAnalyzer;

$report = (new PortabilityAnalyzer)->analyze($query);
$report->classification();   // PortabilityClass
$report->percent();          // 100.0 if everything is portable
$report->differences;        // list<string>
```

It detects JSON, date extraction, string aggregation, `ANY_VALUE`, `ILIKE`,
regex, `IS DISTINCT FROM`, JSON contains, LATERAL, row locking, recursive CTE,
upsert and RETURNING. `QueryBuilder::portability()` exposes it.

## PerformanceAnalyzer

Static evaluation **before executing** (it does not invent plans or timings):

```php
use OrmUniversal\Contracts\Enums\HydrationMode;
use OrmUniversal\Relational\Optimizer\PerformanceAnalyzer;

$report = (new PerformanceAnalyzer)->analyze($query, HydrationMode::Light);
$report->estimatedComplexity;   // low | medium | high
$report->hydrationCost;         // minimal | low | medium | high
$report->streamingAvailable;    // true
$report->potentialNPlusOne;     // false
$report->notes;                 // suggestions about limit, joins, subqueries
```

Complexity adds joins + unions + groups + 2×subqueries; hydration cost
maps the `HydrationMode` (Zero → minimal, Eloquent → high).
`QueryBuilder::performance()` exposes it.

`explain()` is **not** static analysis: it runs the engine's real EXPLAIN and
throws `UnsupportedFeatureException` if `Capability::EXPLAIN` does not exist.

See [Performance](performance.md), [Expressions](expressions.md) and
[Dialects](dialects.md).
