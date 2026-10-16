# Eloquent — OrmUniversal\Laravel

The integration is additive: **Eloquent is neither modified nor wrapped**. The universal
engine coexists with it in hybrid mode.

## Global macros

The provider registers two macros on `Illuminate\Database\Eloquent\Builder`:

```php
Builder::macro('universal', function () {
    return OrmUniversal::table($this->getModel()->getTable(), $this->getModel()->getConnectionName());
});

Builder::macro('recordset', function () {
    return OrmUniversal::table($this->getModel()->getTable(), $this->getModel()->getConnectionName())
        ->recordset();
});
```

Usage:

```php
Seller::query()->universal()->where('is_active', true)->get();
Seller::query()->recordset();   // RecordsetInterface of the whole table
```

The macros do not inject filters or alter the original Eloquent query.

## `HasUniversalQueries` trait

Optional trait (`OrmUniversal\Laravel\Concerns\HasUniversalQueries`) to expose the
universal builder from the model:

| Method | Description |
|---|---|
| `universal(): QueryBuilder` | UDE builder over the model's table/connection |
| `universalTable(): string` | Resolves `getTable()` without touching the real instance |
| `universalConnection(): string` | `getConnectionName()` or `database.default` |
| `universalRecordset(): RecordsetInterface` | Recordset of the whole table |

```php
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

Seller::universal()->where('is_active', true)->recordset();
(new Seller)->universalRecordset();
```

## `LaravelBuilderAdapter`

One-way bridge from an `Eloquent\Builder` to a UDE `QueryBuilder`
(`src/Adapters/LaravelBuilderAdapter.php`):

- `fromEloquent(Builder $builder, QueryExecutor $executor): QueryBuilder`
- `recordset(Builder $builder, QueryExecutor $executor): RecordsetInterface`

Copies **only** the clauses it understands:

| Eloquent clause | UDE translation |
|---|---|
| Basic | `where` / `orWhere` with operator |
| Null / NotNull | `whereNull` / `whereNotNull` |
| In (and not) | `whereIn` / `whereNotIn` |
| Between | `whereBetween` |
| Column | `whereColumn` |
| orders | `orderBy` |
| limit / offset | `limit` / `offset` |

Unknown clauses are **omitted, never guessed**, and the adapter documentation warns: use
it only if the copied clauses cover the entire query.

## Hybrid-mode guarantees

`LaravelCompatibilityLayer::report()` declares the invariants:

- Eloquent results unchanged.
- Ordering, `null` semantics, transactions, casting, and events unchanged.

There are no global interceptors: if you do not call `universal()`, `recordset()`, or the
facade, the behavior is that of pure Laravel.

## When to use each one

- Vanilla Eloquent: models, events, relations, casting, and the entire Laravel ecosystem.
- UDE: read-intensive queries, recordsets/cursor, schema analysis, and
  portability across engines.
