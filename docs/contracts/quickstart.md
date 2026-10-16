# Quickstart — OrmUniversal\Contracts

This package does not execute queries: it defines the stable vocabulary implemented by
`relational`, `nosql`, and `laravel`. The quickstart shows how to consume that vocabulary.

## 1. Negotiated capabilities

`CapabilitySet` is immutable. It is built from canonical names in
`OrmUniversal\Contracts\Support\Capability` or from custom strings.

```php
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;

$capabilities = CapabilitySet::of([Capability::RETURNING, Capability::CTE]);

$capabilities->supports(Capability::CTE);        // true
$capabilities->missing([Capability::JSONB]);     // ['supportsJsonb']
```

If a required capability is missing, negotiation fails with full context:

```php
use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;

try {
    $capabilities->requireAll([Capability::JSONB], 'PostgreSQL', '14.0');
} catch (UnsupportedFeatureException $e) {
    echo $e->report();
    // Feature: supportsJsonb
    // Database: PostgreSQL
    // Server: 14.0
    // Reason: Capability not supported
}
```

`UnsupportedFeatureException` exposes `feature`, `engine`, `engineVersion`, `reason`, and
`alternatives` as public read-only properties.

## 2. Identifiers and SQL fragments

```php
use OrmUniversal\Contracts\ValueObjects\ColumnIdentifier;
use OrmUniversal\Contracts\ValueObjects\SqlFragment;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

$table = TableIdentifier::fromString('public.orders');
$column = ColumnIdentifier::fromString('orders.total');

SqlFragment::trusted('count(*)');                  // explicit trusted SQL
SqlFragment::parameterized('score + ?', [10]);     // SQL with bindings
SqlFragment::identifier('orders.id');              // identifier, gets quoted
SqlFragment::value(42);                            // '?' with binding [42]
```

Contract rule: values never travel raw; they always carry bindings.

## 3. Versions and diagnostics

```php
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

$version = DialectVersion::fromString('18.0');
```

`DialectVersion` allows comparing against documented per-dialect cutoffs. The server
version is what negotiates capabilities, not the driver version.

## 4. When to use each package

| Need | Package |
|---|---|
| Compile SQL, PDO, recordsets, migrations | `OrmUniversal\Relational` |
| Documents, filters, NoSQL pipelines | `OrmUniversal\NoSql` |
| Provider, facade, hybrid mode, `ude:*` commands | `OrmUniversal\Laravel` |

The contracts are meant to be implemented by third parties (plugins). Use
`@api public` as the boundary: everything else is internal to the package.
