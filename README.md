# OrmUniversal Laravel

[![Latest Version](https://img.shields.io/badge/version-0.1.0-blue.svg)](https://github.com/roskiboy/orm-universal-laravel)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-8892BF.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-12.x%20%7C%2013.x-FF2D20.svg)](https://laravel.com)

Capa de integración Laravel de la **Universal Data Platform**: service provider con
auto-discovery, adapters, modo híbrido (Eloquent intacto), puente de modelos y 16 comandos
`ude:*`. El Core (`contracts`, `relational`, `nosql`) no depende de Laravel.

## Instalación

```bash
composer require orm-universal/laravel
php artisan ude:install
```

Publica `config/orm-universal.php` con modos de adopción, N+1, profiler, política de
sentencias destructivas y drivers NoSQL.

## Uso híbrido

```php
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Ast\Column;

// Vanilla Eloquent sigue funcionando sin cambios.
$eloquent = App\Models\Seller::query()->orderBy('name')->pluck('name');

// El mismo resultado por el motor universal.
$ude = OrmUniversal::table('sellers')
    ->where(Column::make('is_active')->eq(true))
    ->orderBy('name')
    ->recordset();
```

Puente Eloquent (opcional):

```php
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

Seller::universal()->where('is_active', true)->get();
Seller::query()->recordset(); // macro global, no altera resultados
```

## Comandos

`ude:install`, `ude:doctor`, `ude:compatibility`, `ude:dialect:list`, `ude:dialect:inspect`,
`ude:schema:inspect`, `ude:schema:diff`, `ude:schema:generate`, `ude:model:inspect`,
`ude:map`, `ude:project:analyze`, `ude:migrate:analyze`, `ude:migrate:codemod`
(dry-run por defecto, nunca escribe silenciosamente), `ude:query:inspect`,
`ude:benchmark`, `ude:cache:clear`.

## Octane / workers

El provider registra `flush()` de contexto y profiler en `RequestTerminated`,
`TaskTerminated`, `TickTerminated`, `JobProcessed` y `JobFailed`. Sin estado global mutable.

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Estado

`0.1.0` · Modo híbrido verificado en el monorepo (`tests/Feature/Ude/SellerUdeTest.php`):
paridad Eloquent↔UDE, recordsets, escrituras y soft deletes explícitos.
