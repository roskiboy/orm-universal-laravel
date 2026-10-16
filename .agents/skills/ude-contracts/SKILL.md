---
name: ude-contracts
description: Stable contracts of OrmUniversal (OrmUniversal\Contracts) - interfaces, enums, value objects, and base exceptions without a Laravel dependency. Use when writing/reading capability negotiation, DialectVersion, CapabilitySet, SqlFragment, QueryFingerprint, UnsupportedFeatureException, or any UDE contract. Triggers: "OrmUniversal\Contracts", "CapabilitySet", "DialectVersion", "SqlFragment", "UnsupportedFeatureException", "UDE contracts".
---

# UDE Contracts (OrmUniversal\Contracts)

## When to use this package

- You need types shared between engines (relational and NoSQL) without pulling in Laravel.
- You are going to negotiate capabilities per engine/version before compiling or executing.
- You need to represent raw SQL in an auditable way (identifier vs value vs fragment).
- You want a stable query identity for cache/profiling (`QueryFingerprint`).
- You need rich errors with engine, version, feature, and documented alternatives.

Do not use it to: compile SQL, connect, hydrate, or inspect schemas. That lives in
`OrmUniversal\Relational` and `OrmUniversal\NoSql`. Repo rule: **importing
`Illuminate\*` is forbidden** here.

## Key APIs (real, verified names)

- Value objects: `OrmUniversal\Contracts\ValueObjects\CapabilitySet`, `DialectVersion`,
  `QueryFingerprint`, `SqlFragment`, `CompiledQuery`, `TableIdentifier`, `ColumnIdentifier`,
  `ConnectionConfig`, `SchemaSnapshot`, `TableDefinition`, `PerformanceReport`,
  `PortabilityReport`.
- Support: `OrmUniversal\Contracts\Support\Capability` (constant catalog) and `ApiStatus`.
- Enums: `DialectVendor`, `DatabaseFamily`, `HydrationMode`, `RecordsetMode`,
  `PerformanceMode`, `CompatibilityMode`, `NPlusOneMode`, `RawType`, `ComparisonOperator`,
  `LogicalOperator`, `JoinType`, `AggregateFunction`, `TypeKind`, among others.
- Exceptions: `PersistenceException` (base with `context()` and `withContext()`) and 12 subclasses,
  including `UnsupportedFeatureException` with `feature`, `engine`, `engineVersion`,
  `reason`, `alternatives`, and `report()`.
- Root interfaces (28): `DialectInterface`, `QueryObjectInterface`, `RecordsetInterface`,
  `HydratorInterface`, `TypeInterface`, `SchemaInspectorInterface`, `ConnectionInterface`,
  `TransactionInterface`, `PluginInterface`, `ProfilerInterface`, etc.

## Recommended patterns

### Negotiate capabilities before compiling

```php
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;

$capabilities = CapabilitySet::of([Capability::RETURNING, Capability::CTE]);
$capabilities->supports(Capability::RETURNING);  // true
$capabilities->missing([Capability::JSONB]);     // ['supportsJsonb']

// Throws UnsupportedFeatureException with feature/engine/version/alternatives
$capabilities->requireAll([Capability::JSONB], 'postgresql', '14.0');
```

`CapabilitySet` is immutable: `with()`, `without()`, and `merge()` return new instances.
Keys are free strings; `Capability` only names the built-in ones so everyone
writes them the same way (`Capability::RETURNING === 'supportsReturning'`).

### Dialect version, never loose strings

```php
use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

$version = DialectVersion::parse(DialectVendor::Sqlite, '3.49.2');
$version->major();                       // 3
$version->atLeast(3, 35);                // true (RETURNING)
$version->sameLine(DialectVersion::parse('sqlite', '3.38.0')); // true
(string) $version;                       // "sqlite 3.49.2"
```

`parse()` accepts the vendor as an enum or a lowercase string; an unknown vendor throws
`InvalidArgumentException`. An empty version does too.

### Auditable raw SQL with `SqlFragment`

```php
use OrmUniversal\Contracts\ValueObjects\SqlFragment;

SqlFragment::trusted('lower(name)');                 // RawType::Trusted
SqlFragment::parameterized('coalesce(total, ?)', [0]); // RawType::Parameterized
SqlFragment::identifier('orders.total');             // RawType::Identifier
SqlFragment::value(42);                              // sql "?", RawType::Value, bindings [42]
```

`SqlFragment::value()` requires at least one binding (the constructor throws
`InvalidArgumentException`). The declared type allows auditing security in strict mode.

### Query identity with `QueryFingerprint`

```php
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;

$a = QueryFingerprint::fromSql('select * from "orders" where total >= 100', 1);
$b = QueryFingerprint::fromSql('select * from "orders" where total >= 999', 1);

$a->normalizedSql;   // literals replaced by "?"
$a->equals($b);      // true: same shape + same bindingCount
(string) $a;         // sha256 hash
```

`normalize()` replaces single- and double-quoted strings and numbers and collapses
whitespace. Note: quoted `"orders"` and unquoted `orders` do **not** produce the same fingerprint.

### Report unsupported features

```php
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;

$exception = UnsupportedFeatureException::forFeature(
    feature: 'supportsAggregation',
    engine: 'redis',
    engineVersion: '5.0.14',
    reason: 'Redis is a key-value store: it has no aggregation pipeline',
    alternatives: ['Aggregate in the application layer', 'Use MongoDB'],
);

echo $exception->report();
// Feature: supportsAggregation
// Database: redis
// Server: 5.0.14
// Reason: ...
// Alternatives:
//   1. ...
//   2. ...
```

## Common mistakes

- Using `new DialectVersion('sqlite', ...)` with a string: the first argument is `DialectVendor`.
  Use `DialectVersion::parse('sqlite', '3.49.2')`.
- Assuming that `CapabilitySet::of([...])` also disables everything else: it only marks what is
  indicated as `true`; everything else is `false`/`null`.
- Expecting fingerprint comparison between SQL with different quoting.
- Creating `SqlFragment::value($v)` without bindings (exception).
- Catching `UnsupportedFeatureException` and losing `alternatives`: always log `report()`.
- Importing `Illuminate\*` in code that belongs to the Core.

## Honest limits

- `contracts` executes nothing: there are no connections, compilers, or hydration.
- Only `psr/log` and `psr/simple-cache` runtime dependencies.
- The enums cover the built-in vendors/features; new ones are registered as free strings
  in `CapabilitySet` and named in a constant if they are to be shared (without touching the Core).
- API `0.1.0` stable under SemVer: an incompatible change breaks relational/nosql/laravel.
- `QueryFingerprint` is heuristic (textual normalization), not a SQL parser.
- `PersistenceException::withContext()` assumes the base constructor signature; in subclasses with
  their own constructor (e.g. `UnsupportedFeatureException`) it is not usable. Use
  `context()`/properties of the concrete exception.
