# PDO

The PDO layer is thin and explicit: it does not interpolate values, it prepares statements and
converts native errors into domain exceptions.

## Default options

`OrmUniversal\Relational\Driver\PdoDriver::connect()` combines your options with
these values. The `+` operator makes **your options take precedence**:

```php
$options = $config->options + [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
];
```

| Option | Value | Reason |
|---|---|---|
| `ATTR_ERRMODE` | `ERRMODE_EXCEPTION` | every PDO failure is an exception |
| `ATTR_DEFAULT_FETCH_MODE` | `FETCH_ASSOC` | associative rows |
| `ATTR_EMULATE_PREPARES` | `false` | native server prepares |
| `ATTR_STRINGIFY_FETCHES` | `false` | preserves native ints/floats |

`PdoConnection` sets `ERRMODE_EXCEPTION` and `FETCH_ASSOC` again in its
constructor when it receives an already-created `PDO`, to guarantee the contract even if
the connection is built by hand.

```php
use OrmUniversal\Relational\Driver\PdoConnection;
use PDO;

$pdo = new PDO('sqlite::memory:', options: [
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$connection = new PdoConnection($pdo, 'sqlite-test');
```

## Bindings

The compiler always emits positional `?` and an ordered array of values
(`CompiledQuery::$bindings`). `PdoConnection` maps each value to its PDO type:

| PHP value | PDO type |
|---|---|
| `null` | `PDO::PARAM_NULL` |
| `bool` | `PDO::PARAM_BOOL` |
| `int` | `PDO::PARAM_INT` |
| rest | `PDO::PARAM_STR` |

Named parameters: `Expression::param('status')` generates a
`ParameterPlaceholder`. `QueryExecutor::withParameters()` or
`QueryBuilder::using(['status' => 'paid'])` substitute them in the final binding.
If the value is missing, `QueryException` with `Missing value for named parameter [:status]`.
If the placeholder reaches the driver unresolved, `PdoConnection` throws
`Unresolved named parameter [:status]`.

```php
$rows = $executor->rows(
    SelectQuery::make('orders')->where(Column::make('status')->eq(Expression::param('status'))),
    ['status' => 'paid'],
);
```

## Execution and reading

- `execute(CompiledQuery): int` → `rowCount()`. For SELECT, `rowCount()` is not
  portable; it is not used as a count.
- `select(CompiledQuery): CursorInterface` → lazy `PdoCursor`.
- `selectOne()` / `selectValue()` → first row / first column.
- `prepare(string $sql): StatementInterface` → statement outside the cache.

## Errors

`PDOException` is wrapped in `QueryException` with context
`['sql' => $query->sql, 'driver' => $driver]`. Credentials are never part
of the message or the context.

```php
use OrmUniversal\Contracts\Exceptions\QueryException;

try {
    $executor->execute(new \OrmUniversal\Relational\Ast\RawQuery('delete from missing where id = ?', [1]));
} catch (QueryException $e) {
    // message + sql/driver context
}
```

For transactions and cursors, see [Drivers](drivers.md). For the
security model of values and identifiers, see [Security](security.md).
