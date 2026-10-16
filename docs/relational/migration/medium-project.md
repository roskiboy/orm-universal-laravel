# Migration: medium project

Medium project: several tables, relations, heavy reports and some shared
services. Adoption is organized by layers, not by files.

## Order of attack

1. **Infrastructure**: one `QueryExecutor` per connection, injected into
   repositories.
2. **Reads**: migrate query repositories (listings, searches).
3. **Relations**: eliminate N+1 with metadata and batch loading.
4. **Writes**: migrate transactional operations.
5. **DDL migrations**: when you need multi-dialect or schema diff.

## Infrastructure

```php
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\ConnectionManager;
use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;

$manager = new ConnectionManager([
    'default' => new ConnectionConfig(driver: 'mysql', host: '127.0.0.1', database: 'app', username: 'app', password: 'secret'),
]);

$connection = $manager->connection('default');
$dialect = DialectResolver::resolve($connection->driverName(), $connection->serverVersion());
$executor = new \OrmUniversal\Relational\Execution\QueryExecutor($connection, $dialect);
```

## Read repository

```php
use OrmUniversal\Relational\Builder\QueryBuilder;

final class InvoiceRepository
{
    public function __construct(private readonly \OrmUniversal\Relational\Execution\QueryExecutor $executor) {}

    public function pending(int $limit = 100): array
    {
        return QueryBuilder::table($this->executor, 'invoices')
            ->where('status', 'pending')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
```

## Eliminate N+1 with relations

```php
use OrmUniversal\Relational\Relation\RelationBatchLoader;
use OrmUniversal\Relational\Relation\RelationDefinition;
use OrmUniversal\Relational\Relation\RelationHydrator;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

$definition = RelationDefinition::hasMany('items', TableIdentifier::fromString('invoice_items'), 'invoice_id')->toMetadata();

$grouped = (new RelationBatchLoader)->load($invoices, $definition, fn (string $table) =>
    QueryBuilder::table($executor, $table)
);
$invoices = (new RelationHydrator)->hydrate($invoices, $definition, $grouped);
```

Register the definitions in `RelationRegistry` to reuse them and plan
with `RelationOptimizer` ([Relations](../relations.md)).

## Large reports

- `chunk($size, $cb)` to process by pages and stop with `false`.
- `cursor()`/`lazy()` for exports.
- `paginate($perPage, $page)` when the UI needs a counter.

## Safe writes

```php
$builder->usingPolicy(new \OrmUniversal\Relational\Execution\DestructiveStatementPolicy(
    \OrmUniversal\Relational\Execution\DestructiveStatementPolicy::WARN,
    $logger,
));

$builder->where('id', $id)->update(['status' => 'paid']);
$builder->upsert($rows, ['number'], ['status', 'total']);
$builder->insertMany($batch); // respects MAX_PLACEHOLDERS
```

## Coexistence testing

- Unit tests with in-memory SQLite ([Quickstart](../quickstart.md)).
- Golden SQL with the real dialect to detect differences
  ([Dialects](../dialects.md)).
- Do not measure performance with a clock; verify plans and query counts.

## Checklist

- [ ] `QueryExecutor` injected, not built per query.
- [ ] Relations registered and batch loading in listings.
- [ ] Explicit destructive policy in services.
- [ ] `->toPlan()` reviewed on complex queries.
