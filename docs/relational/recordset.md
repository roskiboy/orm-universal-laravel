# Recordsets

A recordset is the result of a query, already materialized or streaming.
All of them implement `OrmUniversal\Contracts\RecordsetInterface`, which extends
`Countable`, `IteratorAggregate` and `JsonSerializable`, and offers:

`first()`, `last()`, `current()`, `next()`, `previous()`, `count()`, `exists()`,
`isEmpty()`, `get()`, `has(int|string $key)`, `each(callable)`,
`map(callable)`, `filter(?callable)`, `reduce(callable, mixed $initial = null)`,
`chunk(int $size, callable)`, `lazy(int $chunkSize = 1000)`, `cursor()`,
`stream()`, `toArray()`, `toCollection()`, `paginate(int $perPage = 15, int $page = 1)`,
`getIterator()`.

Semantics of the combinators:

- `each($cb)` returns `$this`; the callback stops if it returns `false`.
- `map($cb)` / `filter(?$cb)` return a **new instance** of the same
  recordset (`new static(...)`).
- `chunk($size, $cb)` delivers one recordset per page and stops if the callback
  returns `false`.
- `toCollection()` returns `collect()` if the function exists (Laravel adapter);
  otherwise an array.

## ArrayRecordset

Materialized in memory. It is the base of the others.

```php
use OrmUniversal\Relational\Recordset\ArrayRecordset;

$recordset = new ArrayRecordset([['id' => 1], ['id' => 2]]);
$recordset->first();            // ['id' => 1]
$recordset->paginate(1, 2);     // Paginator over the slice
```

## StreamingRecordset

Wraps a `callable(): iterable<int, array<string, mixed>>` and an
`?HydratorInterface`. Iteration is lazy; operations that need the
total drain the source and **cache** the rows read (the source is never
re-executed).

```php
use OrmUniversal\Relational\Recordset\StreamingRecordset;

$streaming = new StreamingRecordset(
    fn (): iterable => $executor->cursor($query),
    $hydrator,
);
```

## CursorRecordset

Adapts a `CursorInterface<array<string, mixed>>` (for example `PdoCursor`) and
hydrates on demand without a full buffer. It exposes `cursorSource()`.

## LazyRecordset

Intended for datasets larger than available memory: the source receives
`(int $chunkSize, int $page)` and successive pages are consumed.

```php
use OrmUniversal\Relational\Recordset\LazyRecordset;

$lazy = new LazyRecordset(
    fn (int $chunkSize, int $page): iterable => $executor->rows(
        SelectQuery::make('events')->select('id')->limit($chunkSize)->offset(($page - 1) * $chunkSize),
    ),
    chunkSize: 1000,
    hydrator: null,
);

$lazy->chunkSize();
```

## Content-based modes

- `ObjectRecordset::fromRows(iterable $rows)` — each row is a `stdClass`.
  `fromArray(array $items)` accepts already-built objects.
- `EntityRecordset::fromRows(string $entityClass, iterable $rows)` — hydrates with
  `Hydrator::entity($entityClass)`.
- `EloquentRecordset::fromRows(callable $factory, iterable $rows)` — the
  `callable` builds the model; the core does not know the framework.

## From QueryBuilder

```php
$builder->recordset();     // ArrayRecordset with active hydration
$builder->rawRecordset();  // ArrayRecordset of raw rows
$builder->cursor();        // StreamingRecordset over PdoCursor
```

Additionally, `lazy(int $chunkSize = 1000)`, `chunk(int $size, callable $callback)` and
`paginate(int $perPage = 15, int $page = 1)` are exposed directly on the
builder. `paginate()` returns `OrmUniversal\Relational\Result\Paginator`
(`items()`, `total()`, `perPage()`, `currentPage()`, `lastPage()`,
`hasMorePages()`, `toArray()`).

## Performance and memory

- Materialized recordsets hydrate the whole result; use them with `limit`.
- `cursor()`/`lazy()` keep memory bounded and are the recommended path for
  large tables. See [Performance](performance.md).
- `filter()` and `map()` create copies; in large loops prefer `each()`.

See [ORM](orm.md) for hydration modes and [Query Engine](query-engine.md)
for the execution methods.
