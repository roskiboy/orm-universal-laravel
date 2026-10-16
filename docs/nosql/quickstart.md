# Quickstart — OrmUniversal\NoSql

## 1. Register drivers

`DocumentStore` is a registry and factory. The first registered driver is the default
unless you indicate another with `default: true`.

```php
use OrmUniversal\NoSql\DocumentStore;
use OrmUniversal\NoSql\Driver\InMemoryDriver;
use OrmUniversal\NoSql\Driver\MongoDbDriver;

$store = new DocumentStore();
$store->register('memory', new InMemoryDriver(), default: true);

if (MongoDbDriver::available()) {
    $store->register('mongodb', new MongoDbDriver('mongodb://127.0.0.1:27017', 'app'));
}

$store->drivers();        // ['memory', 'mongodb']
$store->defaultDriver();  // 'memory'
```

## 2. Insert and query

```php
$orders = $store->collection('orders');

$orders->insertMany([
    ['customer' => 'ana', 'total' => 120, 'status' => 'paid'],
    ['customer' => 'bob', 'total' => 80, 'status' => 'pending'],
]);

$paid = $orders->where('status', 'paid')->find();   // list<Document>
$total = $orders->where('total', '>=', 100)->count();
```

`insert()` returns the inserted id; `insertMany()` returns the list of ids.

## 3. Fluent filters

```php
$orders
    ->where('status', 'in', ['paid', 'refunded'])
    ->where('total', '>=', 100)
    ->where('customer', 'exists', true)
    ->find();
```

## 4. Aggregation

```php
use OrmUniversal\NoSql\Aggregation\Pipeline;

$revenue = $store->collection('orders')->aggregate(
    Pipeline::make()
        ->match(['status' => 'paid'])
        ->group(['_id' => '$customer', 'revenue' => ['$sum' => '$total']])
        ->sort(['revenue' => -1]),
);
```

If the driver does not support aggregation (Redis), `UnsupportedFeatureException` is thrown with
documented alternatives; a pipeline is not simulated.

## 5. Documents as value objects

```php
$document = $orders->where('customer', 'ana')->first();

$document?->get('total');   // 120
$document?->toArray();      // full payload
$document['status'];        // ArrayAccess
```

`Document` is immutable: `offsetSet` and `offsetUnset` throw `LogicException`.

## 6. Update and delete

```php
$orders->where('status', 'pending')->update(['$set' => ['status' => 'paid']]);
$orders->where('total', '<', 50)->delete();
```

The fluent filter is reset after `first()`, `update()`, `delete()`, `aggregate()`, and
`each()`. Each operation starts from a clean filter.
