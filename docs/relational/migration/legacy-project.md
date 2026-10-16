# Migration: legacy project

Legacy project: scattered SQL, concatenated queries, little test coverage.
The rule is **do not rewrite blindly**: wrap, measure and replace piece by piece.

## Phase 0: inventory

1. List all raw SQL (searches for `SELECT`, `INSERT`, `UPDATE`,
   `DELETE`, `mysql_query`, `DB::select`, etc.).
2. Classify by risk: read vs write, with/without user input.
3. Freeze behavior: add tests that capture resulting rows before
   touching anything.

## Phase 1: run raw safely

The existing SQL can be executed as is with `RawQuery`, with explicit
bindings:

```php
use OrmUniversal\Relational\Ast\RawQuery;

$rows = $executor->rows(new RawQuery(
    'select id, name from users where status = ? and created_at >= ?',
    ['active', '2026-01-01'],
));
```

Immediate benefit: errors as `QueryException` with `sql` and driver context,
optional profiling and a single PDO layer. Without changing semantics.

For fragments inside a typed query, use
`RawExpression::parameterized($sql, $bindings)`; avoid `trusted()` with input
([Security](../security.md)).

## Phase 2: convert by shape

| Legacy SQL | Typed replacement |
|---|---|
| `WHERE a = ?` | `->where('a', $v)` |
| `WHERE a IN (?, ?)` | `->whereIn('a', $values)` |
| `WHERE a IS NULL` | `->whereNull('a')` |
| `BETWEEN ? AND ?` | `->whereBetween('a', $low, $high)` |
| `JOIN` with textual `ON` | `->join('b', 'b.id', '=', 'a.b_id')` |
| `EXISTS (SELECT ...)` | `->whereExists($select)` |
| `LIMIT/OFFSET` | `->limit()->offset()` or `paginate()` |

Every conversion is verified with `->sql()` and `->bindings()` against the
original SQL, even before connecting to the database.

## Phase 3: writes and transactions

```php
$tx = $executor->transaction();
$tx->begin();

try {
    $builder->where('id', $id)->update(['status' => 'processed']);
    $tx->commit();
} catch (\Throwable $e) {
    $tx->rollBack();
    throw $e;
}
```

Move writes without WHERE through `DestructiveStatementPolicy::WARN` first and
`DENY` once the inventory is clean.

## Phase 4: strict mode

The platform adapter documents `strict` mode, where unclassified legacy SQL
fails. Enable it **after** the golden queries cover the critical
flows ([Testing](../testing.md)).

## Checklist

- [ ] Raw SQL inventory with classified risk.
- [ ] Capture tests before converting.
- [ ] `RawQuery`/`RawExpression::parameterized()` for everything pending.
- [ ] Conversions verified by SQL + bindings.
- [ ] Destructive policy `WARN` → `DENY`.
- [ ] Strict mode only at the end.
