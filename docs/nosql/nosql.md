# NoSQL — OrmUniversal\NoSql

Inventory of the real API in `src/`. The NoSQL engine is conceptually independent from the
SQL engine: it does not translate documents to SQL or pipelines to JOINs.

## `DocumentStore`

Driver registry and factory (`src/DocumentStore.php`).

| Method | Description |
|---|---|
| `register(string $name, DocumentDriverInterface $driver, bool $default = false): void` | Registers a driver; the first one becomes the default |
| `driver(?string $name = null): DocumentDriverInterface` | Gets the driver or throws `DriverException` |
| `collection(string $collection, ?string $driver = null): Collection` | Creates a collection over the driver |
| `drivers(): array` | Registered names |
| `defaultDriver(): ?string` | Name of the default driver |

## `Collection`

Fluent facade over a driver (`src/Collection.php`). It keeps a single pending filter.

| Method | Description |
|---|---|
| `where(string $field, mixed $operator = null, mixed $value = null): self` | Adds a predicate; with 2 arguments it assumes `eq` |
| `insert(array $document): int\|string` | Inserts a document |
| `insertMany(array $documents): array` | Inserts and returns ids |
| `find(): array` | Returns `list<Document>` |
| `first(): ?Document` | First document; resets the filter |
| `update(array $update): int` | Updates the documents matching the filter |
| `delete(): int` | Deletes the documents matching the filter |
| `count(): int` | Counts matches |
| `aggregate(?Pipeline $pipeline = null): array` | Runs the pipeline; prepends the pending filter as `$match` |
| `each(callable $callback): void` | Iterates; `return false` breaks |
| `drop(): void` | Drops the collection |
| `name()`, `driver()`, `filter()` | Accessors |

Operators accepted in `where()`: `=`, `eq`, `!=`, `ne`, `neq`, `>`, `gt`, `>=`, `gte`,
`<`, `lt`, `<=`, `lte`, `in`, `exists`, `regex`.

## `Document`

Immutable value object (`src/Document.php`) with `ArrayAccess` and `JsonSerializable`.

- `fromArray(array $data, int|string|null $id = null)`: infers the id from `_id` or `id`.
- `get($key, $default = null)`, `has($key)`, `__get`, `toArray()`, `jsonSerialize()`.
- `offsetSet`/`offsetUnset` throw `LogicException` (immutable).

## `Filter`

Neutral filter builder (`src/Filter.php`) that produces operator documents
such as `['age' => ['$gte' => 18], '$or' => [...]]`.

Methods: `make()`, `eq()`, `neq()`, `gt()`, `gte()`, `lt()`, `lte()`, `in()`, `exists()`,
`regex()`, `and()`, `or()`, `toArray()`, `jsonSerialize()`.

## `Aggregation\Pipeline`

Pipeline of native stages (`src/Aggregation/Pipeline.php`): `make()`, `match()`,
`group()`, `sort()`, `limit()`, `skip()`, `project()`, `unwind()`, `stage()`, `stages()`.

## `Driver\DocumentDriverInterface`

Backend contract (`src/Driver/DocumentDriverInterface.php`): `name()`,
`capabilities()`, `supports()`, `insertMany()`, `find()`, `findOne()`, `updateMany()`,
`deleteMany()`, `count()`, `aggregate()`, `drop()`, `collections()`.

Each driver declares its real `CapabilitySet`; the absence of a capability must be reflected
in `supports()` and in an honest `UnsupportedFeatureException`.
