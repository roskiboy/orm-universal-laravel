# Ejemplos — orm-universal/laravel

Ejemplos ejecutables contra la API real del paquete.

## Lectura con la fachada y modos de hidratación

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;

$builder = OrmUniversal::table('sellers')
    ->where('is_active', true)
    ->orderBy('name');

$arrays = $builder->get();               // hidratación por defecto
$objects = OrmUniversal::table('sellers')->light()->get();
$entity = OrmUniversal::table('sellers')->entity(App\Entities\Seller::class)->get();
$models = OrmUniversal::table('sellers')->models(fn ($row) => new App\Models\Seller((array) $row))->get();
```

## Recordsets y streaming

```php
use OrmUniversal\Contracts\RecordsetInterface;

$recordset = OrmUniversal::table('orders')->recordset();
$recordset->count();
$recordset->map(fn ($row) => $row['total'] * 1.21)->toArray();

OrmUniversal::table('orders')->lazy(500)->each(function ($row) {
    // procesa por lotes sin materializar todo
});

OrmUniversal::table('orders')->chunk(1000, function ($records) {
    return true; // false detiene
});
```

## Modelo Eloquent con trait

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

## Macros desde cualquier Builder

```php
$ude = Seller::query()
    ->where('is_active', true)
    ->orderBy('name')
    ->universal();

$ude->capabilities();
$ude->fingerprint();
$ude->sql();
```

## Puente de cláusulas Eloquent

```php
use OrmUniversal\Laravel\Adapters\LaravelBuilderAdapter;

$adapter = new LaravelBuilderAdapter();
$recordset = $adapter->recordset(
    Seller::query()->where('is_active', true)->orderBy('name')->limit(10),
    OrmUniversal::executor(),
);
```

Solo si las cláusulas copiadas cubren la consulta completa; las desconocidas se omiten.

## Inspección de esquema

```bash
php artisan ude:schema:inspect orders --json
php artisan ude:schema:diff --snapshot=storage/ude/schema.json
```

## NoSQL desde el kernel

```php
$nosql = OrmUniversal::nosql();
$nosql->register('memory', new \OrmUniversal\NoSql\Driver\InMemoryDriver(), default: true);

$nosql->collection('events')->insert(['type' => 'login', 'user_id' => 42]);
$recent = $nosql->collection('events')->where('type', 'login')->find();
```

## Profiler

```php
$events = OrmUniversal::profiler()->events();
OrmUniversal::kernel()->flush(); // reinicia contextos y profiler
```
