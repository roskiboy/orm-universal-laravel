# Security

The model is simple and auditable: **values are never interpolated** and **raw SQL
must declare what it is**. These guarantees live in the compiler and can be
verified in the tests.

## 1. Values always as bindings

`CompileContext::bind(mixed $value): string` adds the value to the ordered list
and returns `?`. `Value` and the scalars of `FunctionCall`/`InList` go through it.
The resulting SQL never contains the value:

```php
$compiled = $dialect->compile(
    SelectQuery::make('users')->where('name', "O'Neil; drop table users"),
);
// $compiled->sql      => select ... where "name" = ?
// $compiled->bindings => ["O'Neil; drop table users"]
```

Named placeholders (`Expression::param('status')`) are resolved in
`QueryExecutor::withParameters()`; if the value is missing, a `QueryException`
is thrown before touching the database.

## 2. Quoted and validated identifiers

The compiler quotes every identifier with `AbstractDialect::quoteIdentifier()`,
which escapes the engine's quote character (`` ` ``, `"` or `[]`). `Column` and
`TableIdentifier` do not contain values.

For raw fragments declared as identifiers, `CompileContext` applies
`strictIdentifiers` (by default `true`): the compiler validates
`^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$` and throws
`QueryException::unsafeIdentifier()` if it does not comply.

## 3. RawType: four levels of explicitness

`RawExpression` wraps `SqlFragment`, which declares its nature:

| `RawType` | Factory | Meaning |
|---|---|---|
| `Trusted` | `RawExpression::trusted($sql)` / `SqlFragment::trusted()` | developer SQL with no external input |
| `Parameterized` | `RawExpression::parameterized($sql, $bindings)` / `SqlFragment::parameterized()` | SQL with its own bindings |
| `Identifier` | `SqlFragment::identifier($identifier)` | identifier validated at runtime |
| `Value` | `SqlFragment::value($value)` | value that travels as a binding (`?`) |

`SqlFragment` with `RawType::Value` requires at least one binding; the constructor
throws `InvalidArgumentException` if there is none.

```php
use OrmUniversal\Relational\Ast\RawExpression;

$raw = RawExpression::parameterized('lower(name) = lower(?)', ['ana']);
$raw->rawType(); // RawType::Parameterized
```

Review rule: `trusted()` with concatenated user input is a
security finding; use `parameterized()` or typed expressions.

## 4. Destructive statement policy

`DestructiveStatementPolicy` protects UPDATE/DELETE without WHERE:

| Constant | Behavior |
|---|---|
| `ALLOW` | allows everything |
| `WARN` (default) | warns via logger and continues |
| `REQUIRE_CONFIRMATION` | requires `confirmDestructive()` |
| `DENY` | throws `QueryException::destructiveStatement()` |

```php
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;

$builder
    ->usingPolicy(new DestructiveStatementPolicy(DestructiveStatementPolicy::DENY, $logger))
    ->where('id', 1)
    ->delete();
```

With a WHERE present the policy does not intervene. `Validator::warnings()` additionally
reports UPDATE/DELETE without WHERE as a non-fatal warning.

## 5. CrossDatabaseCapability

Joins between connections are not portable. The guard fails explicitly:

```php
use OrmUniversal\Relational\Support\CrossDatabaseCapability;

$guard = new CrossDatabaseCapability;
$guard->supportsCrossDatabaseJoin();     // false
$guard->supportsCrossConnectionJoin();   // false
$guard->assertJoinAllowed('default', 'reporting'); // UnsupportedFeatureException
$guard->report();
```

Documented alternatives: join in the application, ETL/materialized view or the
engine's federated capability when it exists.

## 6. Other controls

- Savepoint names validated (`^[A-Za-z_][A-Za-z0-9_]*$`) in
  `PdoTransaction`.
- PDO errors wrapped in `QueryException` with `sql`/`driver` context, without
  credentials.
- `InMemoryProfiler` is bounded (500 events) so it does not grow without limit.

See [PDO](pdo.md), [Dialects](dialects.md) and [Troubleshooting](troubleshooting.md).
