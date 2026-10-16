# Expressions

`OrmUniversal\Relational\Ast\Expression` extends `Node` and implements
`ExpressionInterface`. It is the object-oriented API that converges on the same
AST as `QueryBuilder`. Every expression can carry an alias with `as()` and
be queried with `alias()`.

## Static factories

```php
use OrmUniversal\Relational\Ast\Expression;

Expression::column('users.id');          // Column
Expression::value(100);                  // Value (always binding)
Expression::param('status');             // Named Parameter
Expression::raw('coalesce(a, b)');       // Trusted RawExpression
Expression::fragment($sqlFragment);      // RawExpression from SqlFragment
Expression::func('upper', 'name');       // FunctionCall
Expression::json('meta', '$.type', true);// JsonExpression asText
Expression::date('year', 'created_at');  // DateExpression
```

## Comparison operators

| Method | Result |
|---|---|
| `eq($v)` / `neq($v)` | `Comparison` = / `<>` |
| `gt($v)` / `gte($v)` | `>` / `>=` |
| `lt($v)` / `lte($v)` | `<` / `<=` |
| `like($pattern)` / `notLike($pattern)` | `LIKE` / `NOT LIKE` |
| `isNull()` / `isNotNull()` | `IS NULL` / `IS NOT NULL` |
| `in(array $values)` / `notIn(array)` | `InList` |
| `between($low, $high)` / `notBetween(...)` | `Between` |
| `logical(LogicalOperator $op, Expression ...$e)` | `LogicalExpression` |
| `and(Expression ...$e)` / `or(Expression ...$e)` | AND / OR |

Values are normalized with `Expression::normalizeOperand()`: an `Expression`
is kept, a `SqlFragment` is wrapped in `RawExpression` and the rest is
converted into a `Value`.

```php
use OrmUniversal\Relational\Ast\Column;

$predicate = Column::make('total')->gte(100)
    ->and(Column::make('status')->eq('paid'))
    ->or(Column::make('refunded')->isNull());
```

## Aggregates and ordering

```php
Column::make('id')->count();      // Aggregate Count
Column::make('total')->sum();
Column::make('total')->avg();
Column::make('total')->min();
Column::make('total')->max();

Column::make('total')->desc();    // Ordering desc
Column::make('total')->asc();
```

`Column::make('id')->as('total_id')` applies a projection alias.

## Concrete nodes

- **`Column(string $name, ?string $table = null)`** — `make('t.c')`,
  `of('t', 'c')`, `qualifiedName()`. Never contains values.
- **`Value(mixed $value)`** — literal; the compiler emits it as `?`.
- **`Parameter(?string $name = null)`** — placeholder resolved by
  `QueryExecutor::withParameters()` or `QueryBuilder::using()`.
- **`RawExpression`** — wraps a `SqlFragment`; `trusted()`,
  `parameterized()`, `rawType()`. See [Security](security.md).
- **`Comparison(Expression $left, ComparisonOperator $operator, ?Expression $right)`**
  — `Comparison::of()` maps strings (`=`, `!=`, `>`, `like`, `ilike`,
  `is distinct from`, ...). `IsNull`/`IsNotNull` carry `right = null`.
- **`LogicalExpression(LogicalOperator $operator, array $expressions)`** —
  `parts()`; the compiler flattens groups of the same operator.
- **`Between(Expression $expression, Expression $low, Expression $high, bool $negated)`**.
- **`InList(Expression $expression, array|SelectQuery $values, bool $negated)`** —
  besides lists it accepts subqueries (`new InList($col, $select, false)`).
- **`Exists(SelectQuery $query, bool $negated)`** — `whereExists()` builds it.
- **`FunctionCall(string $name, array $arguments = [], bool $distinct = false)`** —
  `over(?WindowExpression)`; unknown native functions are compiled as
  is.
- **`Aggregate(AggregateFunction $function, Expression|string $expression = '*', bool $distinct = false, ?string $separator = null)`**
  — `countAll()`, `asCount()`, `over()`. Dialects map `GroupConcat`,
  `StringAgg`, `AnyValue`, `Median`, `Percentile`.
- **`CaseExpression`** — `make(?Expression $case)`, `when($condition, $result)`,
  `then($result)`, `else($result)` (immutable).
- **`Subquery(SelectQuery $query)`** — scalar expression.
- **`AliasedExpression(Expression $expression, string $alias)`** — explicit
  alias.
- **`WindowExpression`** — `make($function)`, `partitionBy(...)`,
  `orderBy(Ordering ...)`, `frame(string)`, `named(WindowExpression)`.
- **`JsonExpression`** — `Expression::json($column, $path, $asText)`, `text()`,
  `contains($value)`, `containsKey($key)`.
- **`DateExpression`** — `Expression::date($part, $column)` or
  `DateExpression::of($part, $column)`.
- **`StringExpression` / `NumericExpression`** — markers that extend
  `FunctionCall` (no methods of their own); they allow classifying portability.

## Integrated example

```php
use OrmUniversal\Relational\Ast\Aggregate;
use OrmUniversal\Relational\Contracts\Enums\AggregateFunction;

$query = SelectQuery::make('orders')
    ->select(
        Column::make('region'),
        new Aggregate(AggregateFunction::Sum, Column::make('total')),
    )
    ->where(Column::make('created_at')->gte('2026-01-01'))
    ->groupBy(Column::make('region'))
    ->having(Column::make('total')->sum()->gt(1000));
```

See [AST](ast.md), [Query Engine](query-engine.md) and
[Portability](optimizer.md).
