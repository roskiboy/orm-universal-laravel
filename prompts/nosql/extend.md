# Prompt: extend OrmUniversal\NoSql (driver or engine)

## Context

The extension contract is `OrmUniversal\NoSql\Driver\DocumentDriverInterface`. A new driver
must not fake capabilities: it declares its `CapabilitySet` and throws
`UnsupportedFeatureException` when it does not implement something.

## Goal

Ask an agent to create a new driver (e.g. for Elasticsearch, DynamoDB, or a proprietary
store):

1. Implement the full interface:

```php
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;
use OrmUniversal\NoSql\Driver\DocumentDriverInterface;

final class MyDriver implements DocumentDriverInterface
{
    public function name(): string { return 'mydb'; }
    public function capabilities(): CapabilitySet { /* ... */ }
    public function supports(string $capability): bool { /* ... */ }
    public function insertMany(string $collection, array $documents): array { /* ids */ }
    /** @return list<Document> */
    public function find(string $collection, array $filter = [], array $options = []): array { /* ... */ }
    public function findOne(string $collection, array $filter = []): ?array { /* ... */ }
    public function updateMany(string $collection, array $filter, array $update): int { /* ... */ }
    public function deleteMany(string $collection, array $filter): int { /* ... */ }
    public function count(string $collection, array $filter = []): int { /* ... */ }
    public function aggregate(string $collection, Pipeline $pipeline): array { /* ... */ }
    public function drop(string $collection): void { /* ... */ }
    /** @return list<string> */
    public function collections(): array { /* ... */ }
}
```

2. If the backend has no pipelines, throw `UnsupportedFeatureException` with alternatives
   (the exact pattern of `RedisDriver::aggregate()`), instead of simulating.
3. Return `Document` in `find()` and arrays in `findOne()`; respect `Document::fromArray()`.
4. Register the driver in `DocumentStore`:

```php
$store->register('mydb', new MyDriver(...), default: true);
$store->collection('orders', 'mydb');
```

5. Add contract tests against `InMemoryDriver` (same semantics) and conditional
   real tests with skip if the backend is not available.

## Rules

- Backend-neutral filters (`['field' => ['$gte' => 18]]`) or documented native ones.
- `_id` in `Document` must be `int|string|null`; convert exotic types.
- Do not declare `supportsX` without implementation + test.
- Document limits (client-side filtering, no transactions, no aggregation, TTL, etc.).

## Verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
