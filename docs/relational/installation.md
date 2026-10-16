# Installation

`OrmUniversal\Relational` is the relational engine of the Universal Data Platform:
AST, expressions, SQL compiler, versioned dialects, PDO layer, recordsets,
hydration, schema/migrations, optimizer, relations and business logic.
**It does not depend on Laravel.**

## Requirements

| Requirement | Version / detail |
|---|---|
| PHP | `^8.3` (the package CI tests 8.3, 8.4 and 8.5) |
| `ext-pdo` | Mandatory (connection layer) |
| `ext-json` | Mandatory (JSON compilation, fingerprint, types) |
| `OrmUniversal\Contracts` | `^0.1`, installed as a dependency |

Optional PDO extensions depending on the engine (declared in `suggest`):

| Extension | Engine |
|---|---|
| `ext-pdo_mysql` | MySQL and MariaDB |
| `ext-pdo_pgsql` | PostgreSQL |
| `ext-pdo_sqlite` | SQLite |
| `ext-pdo_sqlsrv` | SQL Server |
| `ext-pdo_oci` | Oracle |

## Installing with Composer

```bash
composer require roskiboy/orm-universal-laravel
```

## Verifying the installation

```php
<?php

declare(strict_types=1);

use OrmUniversal\Relational\Dialect\SqliteDialect;

require __DIR__.'/vendor/autoload.php';

$dialect = SqliteDialect::make('3.49.2');

echo $dialect->name();        // sqlite-3.49.2
var_dump($dialect->supports('supportsReturning')); // true (SQLite >= 3.35)
print_r($dialect->limitations());                  // documented limitations
```

Compiling a query needs neither a connection nor a database:

```php
use OrmUniversal\Relational\Ast\SelectQuery;

$compiled = $dialect->compile(
    SelectQuery::make('users')->select('id')->where('active', true),
);

echo $compiled->sql;        // select "id" from "users" where "active" = ?
var_dump($compiled->bindings); // [true]
```

## Checking available PDO drivers

```php
var_dump(\PDO::getAvailableDrivers());
```

`OrmUniversal\Relational\Driver\PdoDriver::requiredExtensions()` returns
`['pdo']`; `PdoDriver::supports($config)` compares the requested driver against
`PDO::getAvailableDrivers()` and `PdoDriver::connect()` throws
`OrmUniversal\Contracts\Exceptions\DriverException` if the extension is not
installed.

## Next steps

- [Quickstart](quickstart.md): first query against in-memory SQLite.
- [Architecture](architecture.md): pipeline AST → capabilities → compiler → PDO.
- [Dialects](dialects.md): supported versions and capability negotiation.

## Framework integration

The package is Core without Laravel. Adapters live in the monorepo
(`OrmUniversal\Laravel`, `OrmUniversal\NoSql`); see
[Migration from Laravel](migration-from-laravel.md) for the hybrid mode.
