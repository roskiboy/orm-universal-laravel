# Drivers

The connection layer lives in `OrmUniversal\Relational\Driver` and implements the
`DriverInterface`, `ConnectionInterface`, `StatementInterface`,
`CursorInterface` and `TransactionInterface` contracts.

## PdoDriver

```php
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;
use OrmUniversal\Relational\Driver\PdoDriver;

$driver = new PdoDriver;

$config = new ConnectionConfig(
    driver: 'pgsql',
    host: '127.0.0.1',
    port: 5432,
    database: 'app',
    username: 'app',
    password: 'secret',
    charset: 'utf8',
    options: [],
);

$driver->supports($config);        // is the PDO extension available?
$driver->requiredExtensions();     // ['pdo']
$connection = $driver->connect($config);
```

`connect()` validates against `PDO::getAvailableDrivers()` and throws `DriverException`
if the extension is missing or the connection fails. `ConnectionConfig::toDsn()` builds
the DSN for `mysql`/`mariadb`, `pgsql`, `sqlite`, `sqlsrv` and `oci`.

## PdoConnection

Real `ConnectionInterface`:

- `select(CompiledQuery): CursorInterface`
- `selectOne(CompiledQuery): ?array`
- `selectValue(CompiledQuery): mixed`
- `execute(CompiledQuery): int`
- `prepare(string $sql): StatementInterface`
- `transaction(): TransactionInterface`
- `lastInsertId(?string $sequence = null)`
- `driverName()`, `serverVersion()`, `ping()`, `close()`, `rawPdo()`, `isClosed()`
- `runCompiled(string $sql, array $bindings = []): int`
- `name()`

The constructor sets `PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION` and
`PDO::ATTR_DEFAULT_FETCH_MODE = FETCH_ASSOC`. `QueryExecutor::withParameters()`
replaces the `ParameterPlaceholder`s with their values; if one reaches the connection
unresolved, the latter throws `QueryException` with the parameter name.

## Statement cache

`PdoConnection` keeps a cache of prepared statements keyed by SQL:

- Size via constructor: `new PdoConnection($pdo, $name, $profiler, $statementCacheSize = 128)`.
- Key: `hash('xxh128', $sql)`.
- `statementCacheSize <= 0` disables the cache.
- When full, the oldest entry is discarded (`array_shift`).

Every execution maps positional bindings to PDO types: `PARAM_NULL`, `PARAM_BOOL`,
`PARAM_INT` and `PARAM_STR` for the rest.

## PdoStatement

Wrapper around `PDOStatement` with `execute(array $bindings = [])`, `fetch()`,
`fetchAll()`, `fetchColumn(int $column = 0)`, `rowCount()`, `close()` and `sql()`.
PDO errors are converted into `QueryException` with `['sql' => ...]`.

## PdoCursor

Forward-only cursor over a `PDOStatement`:

- `getIterator()` emits rows and stores them in an internal buffer.
- `count()` and `toArray()` drain the source only once.
- `close()` closes the native cursor and records the final event in the profiler
  (`QueryProfileEvent` with `rows` and `memoryBytes`).

It does not support backward movement: `previous()` does not exist on the cursor;
materialized recordsets do offer it by moving their position.

## PdoTransaction

Transaction with explicit state (`TransactionState`) and savepoints:

```php
$tx = $connection->transaction();
$tx->begin();
$tx->savepoint('step1');
// ...
$tx->rollbackTo('step1');
$tx->releaseSavepoint('step1');
$tx->commit();

$tx->state();              // TransactionState
$tx->level();              // current level
$tx->isActive();
$tx->supportsSavepoints();
```

`begin()` on an active transaction throws `ConnectionException`. Savepoint names
are validated with `^[A-Za-z_][A-Za-z0-9_]*$` before execution.

## ConnectionManager

Multi-connection manager with lazy creation:

```php
use OrmUniversal\Relational\Driver\ConnectionManager;

$manager = new ConnectionManager([
    'default' => new ConnectionConfig(driver: 'sqlite', database: ':memory:'),
    'reporting' => new ConnectionConfig(driver: 'pgsql', database: 'reporting'),
]);

$manager->has('reporting');
$manager->names();
$manager->connection();            // 'default'
$manager->connection('reporting');
$manager->add('audit', $config);
$manager->close('reporting');
$manager->purge();                 // closes all (Octane, queues)
```

Joins between different connections are not portable: use
`CrossDatabaseCapability::assertJoinAllowed()` first. See [Security](security.md),
[PDO](pdo.md) and [Contexts](architecture.md).
