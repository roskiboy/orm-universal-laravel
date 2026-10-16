# Quickstart

This guide creates a SQLite dialect, opens an in-memory PDO connection and runs
real queries with `QueryBuilder` on the same AST used by the object-oriented
API. It requires neither Laravel nor a database server.

## 1. Create the dialect

```php
use OrmUniversal\Relational\Dialect\SqliteDialect;

$dialect = SqliteDialect::make('3.49.2');
```

`SqliteDialect::make(?string $version = null)` uses `3.49.2` by default. There are also
`MySqlDialect::make()`, `MariaDbDialect::make()`,
`PostgreSqlDialect::make()`, `SqlServerDialect::make()` and `OracleDialect::make()`
(see [Dialects](dialects.md)).

## 2. Open the connection and the executor

```php
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;

$connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-test');
$executor = new QueryExecutor($connection, $dialect);
```

`PdoConnection` forces `PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION` and
`PDO::ATTR_DEFAULT_FETCH_MODE = FETCH_ASSOC`. If you prefer to build it from
configuration, use the driver:

```php
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;
use OrmUniversal\Relational\Driver\PdoDriver;

$connection = (new PdoDriver)->connect(new ConnectionConfig(
    driver: 'sqlite',
    database: ':memory:',
));
```

## 3. Prepare the schema

Explicit DDL is executed as a `RawQuery`; for portable DDL see
[Migrations](migrations.md).

```php
use OrmUniversal\Relational\Ast\RawQuery;

$executor->execute(new RawQuery(
    'create table users (id integer primary key autoincrement, name text not null, age integer not null default 0, active integer not null default 1)',
));
```

## 4. Query with QueryBuilder

```php
use OrmUniversal\Relational\Builder\QueryBuilder;

$users = QueryBuilder::table($executor, 'users');

$users->insert([
    ['name' => 'ana', 'age' => 30],
    ['name' => 'bob', 'age' => 25],
    ['name' => 'carla', 'age' => 40],
]);

$rows = $users
    ->select('name', 'age')
    ->where('active', true)
    ->whereIn('age', [25, 30])
    ->orderBy('age', 'asc')
    ->limit(2)
    ->get();

// [['name' => 'bob', 'age' => 25], ['name' => 'ana', 'age' => 30]]
```

`get()` returns associative arrays (`zero` hydration). The object-oriented
API converges on the same AST:

```php
use OrmUniversal\Relational\Ast\Column;

$ana = $users
    ->where(Column::make('age')->gte(18))
    ->where(function ($group): void {
        $group->where('name', 'ana')->orWhere('name', 'carla');
    })
    ->first();
```

## 5. Write, paginate and stream

```php
$affected = $users->where('name', 'bob')->update(['age' => 26]);
$deleted  = $users->where('name', 'carla')->delete();

$page = $users->orderBy('id')->paginate(10, 1);
echo $page->total();

foreach ($users->orderBy('id')->cursor() as $record) {
    // streaming: does not materialize the whole result
}
```

## 6. Inspect without executing

```php
echo $users->where('active', true)->sql();          // compiled SQL
print_r($users->where('active', true)->bindings()); // ordered bindings
echo $users->fingerprint();                          // stable identity
print_r($users->portability()->toArray());           // 100.0 if portable
```

## Next steps

- [Query Engine](query-engine.md): complete fluent API.
- [Expressions](expressions.md): predicates and functions per object.
- [Recordsets](recordset.md) and [ORM hydration](orm.md).
- [PDO](pdo.md): options, bindings and cursors.
