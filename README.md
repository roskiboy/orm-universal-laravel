# ORM Universal Laravel

[![Latest Version](https://img.shields.io/badge/version-0.7.0-blue.svg)](https://github.com/roskiboy/orm-universal-laravel)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-8892BF.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-12.x%20%7C%2013.x-FF2D20.svg)](https://laravel.com)

**ORM Universal Laravel** is the universal data platform for Laravel and PHP in a single
package and repository: stable contracts, a relational engine (AST, SQL compiler,
versioned dialects, PDO layer, recordsets, schema and migrations), a NoSQL engine
(documents, filters, pipelines) and the Laravel integration layer (provider, adapters,
hybrid mode, Eloquent bridge and `ude:*` tooling).

Previously published as four independent packages (`orm-universal/contracts`,
`orm-universal/relational`, `orm-universal/nosql`, `orm-universal/laravel`); this package
declares `replace` for all of them, so migrating projects only change the `require` line.

## Layers

| Namespace | Source | Content |
|---|---|---|
| `OrmUniversal\Contracts\` | `src/Contracts/` | 28 interfaces, enums, value objects, base exceptions |
| `OrmUniversal\Relational\` | `src/Relational/` | AST, expressions, SQL compiler, 6 versioned dialects, PDO driver layer, recordsets, hydration, schema, migrations, optimizer, relations, business logic |
| `OrmUniversal\NoSql\` | `src/NoSql/` | DocumentStore, Collection, Filter, aggregation pipelines (MongoDB, Redis, InMemory) |
| `OrmUniversal\Laravel\` | `src/Laravel/` | Service provider, adapters, hybrid mode, Eloquent bridge, `ude:*` commands |

Dependency rule: `Contracts` ← `Relational`/`NoSql` ← `Laravel`. The Core
(`Contracts`, `Relational`, `NoSql`) never imports `Illuminate\*`.

## Installation

```bash
composer require roskiboy/orm-universal-laravel
php artisan ude:install
```

Publishes `config/orm-universal.php` with adoption modes, N+1, profiler, destructive
statement policy, and NoSQL drivers.

## Hybrid usage (Eloquent untouched)

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

Relational engine without Laravel:

```php
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Dialect\PostgreSqlDialect;

$dialect = PostgreSqlDialect::make('18.0');

$query = SelectQuery::make('orders')
    ->select('id', 'total')
    ->where(Column::make('total')->gte(100))
    ->orderByDesc('total')
    ->limit(50);

$compiled = $dialect->compile($query);

echo $compiled->sql;                // SQL with quoted identifiers
$compiled->bindings;                // [100]
(string) $compiled->fingerprint();  // query identity
```

Optional Eloquent bridge:

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
(dry-run by default, never writes silently), `ude:query:inspect`, `ude:benchmark`,
`ude:parity:api`, `ude:parity:data`, `ude:cache:clear`, `ude:stubs`, among others.

## Octane / workers

The provider registers context and profiler `flush()` on `RequestTerminated`,
`TaskTerminated`, `TickTerminated`, `JobProcessed`, and `JobFailed`. No mutable global
state.

## Tests

```bash
composer install
vendor/bin/phpunit          # Contracts + Relational + NoSql + Laravel suites
vendor/bin/phpstan analyse  # level 5, documented exceptions in phpstan.neon.dist
vendor/bin/pint
```

The suite runs in-memory SQLite by default. Real MariaDB/PostgreSQL/MongoDB/Redis
integration is enabled through the `UDE_*` variables of `phpunit.xml.dist`
(`UDE_INTEGRATION=1`).

Benchmarks (reproducible, SQLite by default):

```bash
php benchmarks/relational/benchmark.php --rows=10000 --assert-vs-pdo=2.0
php benchmarks/laravel/benchmark.php --rows=5000 --json --markdown
```

## Documentation

- `docs/contracts/` – contracts, enums, value objects.
- `docs/relational/` – engine, dialects, PDO, recordsets, schema, migrations.
- `docs/nosql/` – documents, filters, pipelines and drivers.
- `docs/laravel/` – provider, Eloquent bridge, commands, hybrid mode.
- `CHANGELOG.md` – unified history; per-layer historical changelogs under `docs/*/CHANGELOG.md`.

## Status

`0.7.0` – unified package (optimize-14). SQLite and golden tests green; SQL Server and
Oracle implemented at the dialect/DDL level with real integration pending on
infrastructure (not declared stable). See `ROADMAP.md` and `SECURITY.md`.
