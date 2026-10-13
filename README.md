# OrmUniversal Laravel

[![Latest Version](https://img.shields.io/badge/version-0.1.0-blue.svg)](https://github.com/roskiboy/orm-universal-laravel)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-8892BF.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-12.x%20%7C%2013.x-FF2D20.svg)](https://laravel.com)

Laravel integration layer of the **Universal Data Platform**: service provider with
auto-discovery, adapters, hybrid mode (Eloquent untouched), model bridge, and 16 `ude:*`
commands. The Core (`contracts`, `relational`, `nosql`) does not depend on Laravel.

## Installation

```bash
composer require orm-universal/laravel
php artisan ude:install
```

Publishes `config/orm-universal.php` with adoption modes, N+1, profiler, destructive
statement policy, and NoSQL drivers.

## Hybrid usage

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// Vanilla Eloquent keeps working unchanged.
$eloquent = App\Models\Seller::query()->orderBy('name')->pluck('name');

// The same result through the universal engine.
$ude = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();
```

Eloquent bridge (optional):

```php
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

Seller::universal()->where('is_active', true)->get();
Seller::query()->recordset(); // global macro, does not alter results
```

## Commands

`ude:install`, `ude:doctor`, `ude:compatibility`, `ude:dialect:list`, `ude:dialect:inspect`,
`ude:schema:inspect`, `ude:schema:diff`, `ude:schema:generate`, `ude:model:inspect`,
`ude:map`, `ude:project:analyze`, `ude:migrate:analyze`, `ude:migrate:codemod`
(dry-run by default, never writes silently), `ude:query:inspect`,
`ude:benchmark`, `ude:cache:clear`.

## Octane / workers

The provider registers context and profiler `flush()` on `RequestTerminated`,
`TaskTerminated`, `TickTerminated`, `JobProcessed`, and `JobFailed`. No mutable global
state.

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Status

`0.1.0` · Hybrid mode verified in the monorepo (`tests/Feature/Ude/SellerUdeTest.php`):
Eloquent↔UDE parity, recordsets, writes, and explicit soft deletes.
