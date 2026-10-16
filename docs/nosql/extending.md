# Extending — OrmUniversal\NoSql

You can contribute new backends by implementing `DocumentDriverInterface`. The contract is
small and demands honesty about capabilities.

## 1. Implement the contract

```php
namespace App\NoSql;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;
use OrmUniversal\NoSql\Driver\DocumentDriverInterface;

final class CouchDbDriver implements DocumentDriverInterface
{
    public function name(): string
    {
        return 'couchdb';
    }

    public function capabilities(): CapabilitySet
    {
        return CapabilitySet::of([
            Capability::NOSQL_JSON_DOCUMENTS,
        ]);
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities()->supports($capability);
    }

    public function insertMany(string $collection, array $documents): array { /* ... */ }
    public function find(string $collection, array $filter = [], array $options = []): array { /* list<Document> */ }
    public function findOne(string $collection, array $filter = []): ?array { /* ... */ }
    public function updateMany(string $collection, array $filter, array $update): int { /* ... */ }
    public function deleteMany(string $collection, array $filter): int { /* ... */ }
    public function count(string $collection, array $filter = []): int { /* ... */ }
    public function aggregate(string $collection, Pipeline $pipeline): array { /* ... */ }
    public function drop(string $collection): void { /* ... */ }
    public function collections(): array { /* list<string> */ }
}
```

## 2. Capability rules

- Declare only what is implemented and tested. If there is no aggregation, do not include
  `NOSQL_AGGREGATION`; `aggregate()` must throw `UnsupportedFeatureException` with
  alternatives.
- Use `Capability::coreNoSql()` as the reference for canonical names.
- `find()` returns `list<Document>`; `findOne()` returns `array` or `null`.
- The received filter is a neutral operator document. If your backend is not MongoDB,
  you can evaluate with `FilterMatcher` (like Redis) and document the limitation.

## 3. Register the driver

```php
$store = new DocumentStore();
$store->register('couchdb', new CouchDbDriver(...), default: true);
```

## 4. Laravel integration

The `Ude` kernel registers drivers from `config/orm-universal.php` (`nosql.drivers`).
For your own backends, register the driver in an application service provider after
resolving `Ude` and using `$ude->nosql()->register(...)`. The core does not need to know about it.

## 5. Required tests

- Stable `name()`.
- `capabilities()` consistent with `supports()`.
- Full CRUD with `InMemoryDriver` as the semantics reference.
- Explicit test of missing capabilities (exception with alternatives).
- Real integration marked as skippable if the server is not available.

Do not add backend dependencies to the package `composer.json`: they go in `suggest` and in
the `require-dev` of whoever tests it, as happens with MongoDB and Redis.
