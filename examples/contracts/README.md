# Runnable examples: OrmUniversal\Contracts

Real examples of the Core (interfaces, enums, value objects, and exceptions). They require
no server or database: only PHP 8.3+ and the autoloader.

## Requirements

```bash
# Option A: package dependencies
composer install

# Option B (tms monorepo): the root vendor already contains orm-universal/*
```

Each file loads the autoloader with this fallback logic:

```php
$packageAutoload = __DIR__.'/../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;
```

If the package has its own `vendor/`, it is used; if not (the monorepo case), it falls back to the
root `vendor/autoload.php` of `tms`.

## Execution

```bash
php examples/run.php        # runs the 5 examples and reports
php examples/basic.php      # individual; exit 0 success, exit 1 failure
```

## Files

| File | Contents |
|---|---|
| `basic.php` | `CapabilitySet` and `DialectVersion` (parse, components, comparisons). |
| `intermediate.php` | `SqlFragment` with `RawType` and binding validation. |
| `advanced.php` | `QueryFingerprint`: normalization, equality, hash, and binding count. |
| `expert.php` | `UnsupportedFeatureException::report()` and `PersistenceException` context. |
| `extreme.php` | Full capability negotiation against two synthetic backends. |

All files use verified APIs from `src/` (no invented classes) and end with
`exit(0)` or `exit(1)`.
