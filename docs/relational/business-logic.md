# Business Logic

The business engine lives in `OrmUniversal\Relational\Business`. It resolves
domain intentions (`paid`, `overdue`, `archive`) against an explicit registry.
**Ambiguity throws an exception, it is never guessed**, and every decision
is traced.

## BusinessRegistry

```php
use OrmUniversal\Relational\Business\BusinessRegistry;

$registry = new BusinessRegistry;

$registry->registerScope('paid', fn (object $query): object => $query->where('status', 'paid'), 'Order');
$registry->registerSpecification('highValue', HighValueSpecification::class);
$registry->registerOperation('archive', fn (object $query): string => 'archived', 'Order');
```

API: `registerScope(string $name, callable|string $definition, ?string $model = null)`,
`registerSpecification(string $name, string $specification)`,
`registerOperation(...)`, `hasScope()`, `hasSpecification()`, `hasOperation()`,
`scope()`, `specification()`, `operation()`, `scopes()`, `operations()`.

If a scope exists in several models and `$model` is not given, `scope()` throws
`BusinessOperationException::ambiguousMethod()` with the candidates. A
nonexistent name throws `unknownMethod()`.

`applyScope(string $name, object $query, ?string $model = null)` applies closures
or classes with `apply()`.

## BusinessScope, BusinessOperation and BusinessRule

- `BusinessScope(string $name, $definition, ?string $model = null)` —
  `apply(object $query): object`; the definition accepts a closure or a class with
  `apply()`. `describe()` returns `name`, `model` and `definition`.
- `BusinessOperation(string $name, $handler, ?string $model = null)` —
  `handle(mixed ...$arguments): mixed`; the handler accepts a closure, a class with
  `handle()` or `__invoke()`.
- `BusinessRule(string $name, callable $predicate, string $message = '')` —
  `passes(mixed $subject): bool` and `assert(mixed $subject): void` (throws
  `BusinessOperationException`). Rules do not execute SQL.

## BusinessMethodResolver and MethodCache

Resolves magic methods in documented order: **registered scope first,
then operation**.

```php
use OrmUniversal\Relational\Business\BusinessMethodResolver;

$resolver = new BusinessMethodResolver($registry);
$resolver->supportsScope('paid', 'Order');
$resolver->resolveScope('paid', 'Order')->apply($builder);
$resolver->apply('archive', $builder, 'Order', [$argument]);
$resolver->lastTrace();          // 'operation:archive -> ...'
$resolver->report();             // scopes, operations, cache, last_trace
```

`MethodCache::remember(string $key, callable $resolver): mixed` memoizes
resolutions per instance with `has()`, `forget()`, `clear()` and
`stats(): ['entries','hits','misses']`.

## Specifications and `apply()`

`AbstractSpecification` implements `QuerySpecificationInterface::apply(object $query): void`
and composes:

```php
use OrmUniversal\Relational\Business\AbstractSpecification;

final class HighValueSpecification extends AbstractSpecification
{
    public function apply(object $query): void
    {
        $query->where('total', '>=', 1000);
    }
}

$spec = (new HighValueSpecification)
    ->and(new PaidSpecification)
    ->or(new RefundedSpecification);
```

`AbstractSpecification::and()`, `or()` and `not()` return a
`CompositeSpecification`. `CompositeSpecification` has the constants `AND`, `OR`,
`NOT` and executes `where()` for AND, `orWhere(function ($group) {...})` for OR.
**`NOT` throws `BusinessOperationException`**: there is no portable negation of
a complete specification; express the positive condition.

`QueryBuilder::apply(object $specification)` calls `apply($this)` without executing.
`NestedSpecificationTarget` exists to replicate specifications inside
nested closures.

## Context

```php
use OrmUniversal\Relational\Context\BusinessContext;

$context = BusinessContext::make($registry, tenant: 'acme', userId: 42);
$context->markResolved('scope:paid -> closure');
$context->lastResolution();
$context->toArray(); // tenant, user_id, scopes, last_resolution
```

See [Query Engine](query-engine.md), [Relations](relations.md) and
[Contexts](architecture.md).
