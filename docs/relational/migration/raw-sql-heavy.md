# Migration: raw SQL heavy

If the project depends on handcrafted SQL (complex CTEs, hints, native
functions), there is no need to abandon it. The package allows raw SQL **explicitly** and
safely without losing typed execution.

## RawQuery for complete statements

```php
use OrmUniversal\Relational\Ast\RawQuery;

$rows = $executor->rows(new RawQuery(
    'with recent as (select id from orders where created_at > ?) select count(*) from recent',
    ['2026-01-01'],
));

$executor->execute(new RawQuery('truncate table staging_events'));
```

`RawQuery` also accepts required capabilities and dialect metadata:
`new RawQuery($sql, $bindings, $capabilities, $dialect, $dialectVersion)`.
`compiled()` returns a `CompiledQuery` ready for PDO.

## RawExpression inside typed queries

```php
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Expression;

$query = \OrmUniversal\Relational\Ast\SelectQuery::make('orders')
    ->select(Column::make('id'))
    ->where(Expression::raw('json_extract(meta, \'$.priority\')'), '=', 'high');
```

Levels according to `RawType` ([Security](../security.md)):

| Need | API |
|---|---|
| Fixed developer SQL | `RawExpression::trusted($sql)` |
| SQL with your bindings | `RawExpression::parameterized($sql, $bindings)` |
| Already validated identifier | `SqlFragment::identifier($name)` |
| Value that must travel as a binding | `SqlFragment::value($value)` |

Rule: if the fragment contains user input, **never** `trusted()`.

## Mixing raw with builders

```php
$builder = \OrmUniversal\Relational\Builder\QueryBuilder::table($executor, 'orders')
    ->where('status', 'paid')
    ->where(Expression::raw('total > coalesce(credit_limit, 0)'));

$sql = $builder->sql();          // the rest stays parameterized
$bindings = $builder->bindings(); // only the typed values
```

`explain()` uses `RawQuery('explain '.$compiled->sql, $compiled->bindings)`
internally when the dialect declares `Capability::EXPLAIN`.

## Portability

Mark raw SQL as dialect-specific by design: `PortabilityAnalyzer` cannot
classify free text. If the query must run on several engines,
document the variants and compile with each dialect:

```php
$mysqlSql = \OrmUniversal\Relational\Dialect\MySqlDialect::make('8.4.3')->compile($query)->sql;
$pgsqlSql = \OrmUniversal\Relational\Dialect\PostgreSqlDialect::make('18.0')->compile($query)->sql;
```

## Regression tests

- Capture expected rows per raw query before touching it.
- Add golden SQL if the query can also be compiled typed.
- Verify bindings: `RawQuery` must not carry concatenated values.

## Checklist

- [ ] Every raw fragment declares its `RawType`.
- [ ] Zero concatenation of external input.
- [ ] Per-dialect variants documented and compiled.
- [ ] Golden tests for critical queries.
- [ ] `QueryException` with `sql`/`driver` context when debugging.

See [Security](../security.md), [Dialects](../dialects.md) and
[Legacy project](legacy-project.md).
