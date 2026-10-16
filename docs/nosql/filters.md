# Filters — OrmUniversal\NoSql

Filters are built with `Filter` and produce neutral operator documents
(`['field' => ['$op' => value]]`). The driver decides how to apply them: natively
(MongoDB) or with `FilterMatcher` (InMemory and Redis).

## `Filter` operators

| Method | Generated document |
|---|---|
| `eq($field, $value)` | `[$field => $value]` |
| `neq($field, $value)` | `[$field => ['$ne' => $value]]` |
| `gt`, `gte`, `lt`, `lte` | `[$field => ['$gt' => $value]]`, etc. |
| `in($field, array $values)` | `[$field => ['$in' => [...]]]` |
| `exists($field, bool $exists = true)` | `[$field => ['$exists' => $exists]]` |
| `regex($field, string $pattern)` | `[$field => ['$regex' => $pattern]]` |
| `and(array $conditions)` | Merges conditions at the root level |
| `or(Filter ...$filters)` | `['$or' => [ [...], [...] ]]` |

```php
use OrmUniversal\NoSql\Filter;

$filter = Filter::make(['status' => 'paid'])
    ->gte('total', 100)
    ->in('channel', ['web', 'pos']);

$filter->toArray();
// ['status' => 'paid', 'total' => ['$gte' => 100], 'channel' => ['$in' => ['web','pos']]]
```

## `FilterMatcher`

Evaluator shared by `InMemoryDriver` and `RedisDriver`
(`src/Driver/FilterMatcher.php`). Supports:

- Direct comparison: `$eq`, `$ne`, `$gt`, `$gte`, `$lt`, `$lte`.
- Membership: `$in`, `$nin`.
- Presence: `$exists`.
- Regular expression: `$regex` (delimited with `preg_match`).
- Array size: `$size`.
- Composition: `$or` and `$and` at the root level.
- Dot notation (`customer.address.city`) in `extract()` and `exists()`.

```php
use OrmUniversal\NoSql\Driver\FilterMatcher;

$matcher = new FilterMatcher();
$matcher->matches(['a' => ['b' => 2]], ['a.b' => 2]);       // true
$matcher->matches(['tags' => ['x', 'y']], ['tags' => ['$size' => 2]]); // true
```

## Filters in `Collection`

`Collection::where()` forwards to `Filter`:

```php
$store->collection('orders')
    ->where('status', 'paid')            // implicit eq
    ->where('total', '>=', 100)
    ->where('coupon', 'exists', false)
    ->find();
```

## Honest limitations

- `$regex` is evaluated with a PHP regular expression: review the user's pattern to
  avoid ReDoS; it is not sanitized automatically.
- The direct operator in `FilterMatcher` compares loosely (`==`) for scalars, not
  strictly.
- Redis does not filter on the server: it loads the documents with `mGet` and evaluates in PHP. For
  large volumes, use MongoDB.

See `drivers.md` for the behavior per backend.
