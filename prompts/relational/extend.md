# Prompt: extend OrmUniversal\Relational (dialect, type, business or driver)

## Context

The engine is extensible by registration, without touching the Core. Choose the extension point
according to what you need and respect the existing interfaces.

## 1. New dialect or version line

- Subclass `OrmUniversal\Relational\Dialect\AbstractDialect` (constructor
  `(DialectVersion, CapabilitySet)`) or the closest dialect (`SqliteDialect`, etc.).
- Declare only the capabilities that the official documentation backs; hooks that do not
  apply inherit the conservative ANSI behavior.
- Register it in `DialectRegistry`:

```php
use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

$registry = DialectRegistry::withDefaults();
$registry->register(DialectVendor::Sqlite, fn (DialectVersion $v) => new MyDialect($v), override: true);
$dialect = $registry->for(DialectVersion::parse('sqlite', '3.55.0'));
```

- Mandatory golden SQL and DDL tests; documented `limitations()`.

## 2. New universal type

- Implement `OrmUniversal\Contracts\TypeInterface` or use `Type\UniversalType` with
  `TypeKind::Custom` and `database_type`.
- Register in `TypeRegistry`:

```php
$types = TypeRegistry::withDefaults();
$types->register(new UniversalType('money', TypeKind::Decimal, ['precision' => 19, 'scale' => 4]));
```

- The type must map `databaseType(DialectInterface)`, `phpType()`, `cast()`,
  `serialize()`/`deserialize()`.

## 3. Business logic (scopes/specifications/operations)

- Register in `Business\BusinessRegistry`: `registerScope()`, `registerSpecification()`,
  `registerOperation()`. Keys can be per model (`Seller@activos`).
- The builder applies specifications with `->apply($specification)`; there is no opaque magic:
  resolution is traceable and ambiguity throws `BusinessOperationException`.

## 4. Driver/plugin

- Implement `OrmUniversal\Contracts\PluginInterface` (`name/register/boot`) and register it in
  `Plugin\PluginRegistry`; `Ude::bootPlugins()` runs `registerAll` + `bootAll`.
- For a new PDO driver, wrap `PDO` in `Driver\PdoConnection` and provide the dialect.

## Rules

- Do not modify `contracts` interfaces in a breaking way.
- Honest capabilities: without a documented cutoff, support is not declared.
- Everything with tests (golden/integration) and `phpstan`.
- Document real limits (unverified versions, absent features).

## Verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
