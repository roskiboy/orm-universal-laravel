# Examples — orm-universal/laravel

Examples runnable against the real API of the package.

## Reading with the facade and hydration modes

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;

$builder = OrmUniversal::table('sellers')
    ->where('is_active', true)
    ->orderBy('name');

$arrays = $builder->get();               // default hydration
$objects = OrmUniversal::table('sellers')->light()->get();
$entity = OrmUniversal::table('sellers')->entity(App\Entities\Seller::class)->get();
$models = OrmUniversal::table('sellers')->models(fn ($row) => new App\Models\Seller((array) $row))->get();
```

## Recordsets and streaming

```php
use OrmUniversal\Contracts\RecordsetInterface;

$recordset = OrmUniversal::table('orders')->recordset();
$recordset->count();
$recordset->map(fn ($row) => $row['total'] * 1.21)->toArray();

OrmUniversal::table('orders')->lazy(500)->each(function ($row) {
    // processes in batches without materializing everything
});

OrmUniversal::table('orders')->chunk(1000, function ($records) {
    return true; // false stops
});
```

## Eloquent model with the trait

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

$active = Seller::universal()->where('is_active', true)->recordset();
$all = (new Seller)->universalRecordset();
```

## Macros from any Builder

```php
$ude = Seller::query()
    ->where('is_active', true)
    ->orderBy('name')
    ->universal();

$ude->capabilities();
$ude->fingerprint();
$ude->sql();
```

## Eloquent clause bridge

```php
use OrmUniversal\Laravel\Adapters\LaravelBuilderAdapter;

$adapter = new LaravelBuilderAdapter();
$recordset = $adapter->recordset(
    Seller::query()->where('is_active', true)->orderBy('name')->limit(10),
    OrmUniversal::executor(),
);
```

Only if the copied clauses cover the entire query; unknown ones are omitted.

## Schema inspection

```bash
php artisan ude:schema:inspect orders --json
php artisan ude:schema:diff --snapshot=storage/ude/schema.json
```

## NoSQL from the kernel

```php
$nosql = OrmUniversal::nosql();
$nosql->register('memory', new \OrmUniversal\NoSql\Driver\InMemoryDriver(), default: true);

$nosql->collection('events')->insert(['type' => 'login', 'user_id' => 42]);
$recent = $nosql->collection('events')->where('type', 'login')->find();
```

## Profiler

```php
$events = OrmUniversal::profiler()->events();
OrmUniversal::kernel()->flush(); // resets contexts and profiler
```
