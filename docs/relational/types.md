# Types

The type system lives in `OrmUniversal\Relational\Type` on top of the
`TypeInterface` and `TypeRegistryInterface` contracts. A universal type describes a
logical intention and each dialect decides its DDL syntax.

## UniversalType

```php
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Relational\Type\UniversalType;

$type = new UniversalType('decimal', TypeKind::Decimal, ['precision' => 12, 'scale' => 2]);

$type->name();                        // decimal
$type->kind();                        // TypeKind::Decimal
$type->databaseType($dialect);        // decimal(12,2)
$type->phpType();                     // float|string
$type->cast('12.50');                 // conversion to PHP
$type->serialize(['a' => 1]);         // JSON for bind
$type->deserialize($dbValue);
$type->parameters();
```

`TypeKind` covers: `String`, `Text`, `Integer`, `BigInteger`, `UnsignedInteger`,
`Decimal`, `Float`, `Boolean`, `Date`, `Time`, `DateTime`, `Timestamp`,
`Binary`, `Blob`, `Json`, `Jsonb`, `Uuid`, `Ulid`, `Enum`, `Array`, `Object`,
`Vector`, `Geometry`, `Custom`.

### Real translation per engine

| Kind | MySQL/MariaDB | PostgreSQL | SQLite | SQL Server | Oracle |
|---|---|---|---|---|---|
| String | `varchar(n)` | `varchar(n)` | `varchar(n)` | `nvarchar(n)` | `varchar2(n)` |
| Text | `longtext` | `text` | `text` | `nvarchar(max)` | `clob` |
| Integer | `integer` | `integer` | `integer` | `int` | `number(10)` |
| BigInteger | `bigint` | `bigint` | `bigint` | `bigint` | `number(19)` |
| Boolean | `boolean` | `boolean` | `integer` | `bit` | `number(1)` |
| DateTime | `datetime` | `datetime` | `datetime` | `datetime2` | `date` |
| Blob | `blob` | `bytea` | `blob` | `varbinary(max)` | `blob` |
| Json | `json` (MariaDB `longtext`) | `jsonb`/`json` | `text` | `json` | `json` |
| Uuid | `char(36)` | `uuid` | `char(36)` | `uniqueidentifier` | `raw(16)` |
| Vector | `vector(n)` | `vector(n)` | `vector(n)` | `vector(n)` | `vector(n, float32)` |

`parameters` accepts `length`, `precision`, `scale`, `values` (enum) and
`database_type` (Custom). Specific types keep their identity:
`postgresqlJsonb`, `postgresqlArray`, `postgresqlEnum`, `mysqlJson`,
`mysqlGeometry`, `sqlServerUniqueIdentifier`, `oracleNumber`.

## TypeRegistry

```php
use OrmUniversal\Relational\Type\TypeRegistry;

$registry = TypeRegistry::withDefaults();
$registry->register(new UniversalType('money', TypeKind::Decimal), override: false);
$registry->has('uuid');
$registry->get('integer');
$registry->all();
```

`withDefaults()` pre-registers the 23 universal types and 7 per-engine aliases
(`postgresqlJsonb`, `postgresqlArray`, `postgresqlEnum`, `mysqlJson`,
`mysqlGeometry`, `sqlServerUniqueIdentifier`, `oracleNumber`).
`register()` throws `DialectException` if the name already exists and `override` is
`false`.

## TypeResolver

Translates database names into universal types without hiding differences:

```php
use OrmUniversal\Relational\Type\TypeResolver;

$resolver = new TypeResolver($registry);
$resolver->resolve('varchar(255)');       // string
$resolver->require('uniqueidentifier');   // uuid (or DialectException)
$resolver->resolve('jsonb');              // jsonb
$resolver->forKind(TypeKind::Vector);
$resolver->aliases();
```

The alias parameter strip is real: `number(10,2)` → `number` → `decimal`.
`uuid`, `uniqueidentifier` and `jsonb` are kept; `blob` and `bytea` point to
`blob`.

## Cast, serialization and deserialization

```php
use OrmUniversal\Relational\Type\TypeCaster;
use OrmUniversal\Relational\Type\TypeDeserializer;
use OrmUniversal\Relational\Type\TypeSerializer;

$caster = new TypeCaster;
$caster->cast('5', $registry->get('integer'));            // 5
$caster->castRow($row, ['active' => $registry->get('boolean')]);

$serializer = new TypeSerializer;
$serializer->serialize(['a' => 1], $registry->get('json')); // '{"a":1}'
$serializer->serializeRow($values, $types);

$deserializer = new TypeDeserializer;
$deserializer->deserialize('{"a":1}', $registry->get('json')); // ['a' => 1]
$deserializer->deserializeRow($row, $types);
```

Rules included in `UniversalType`:

- `cast(null)` returns `null`.
- Booleans from string accept `1`, `true`, `t`, `yes`.
- JSON/Array/Object decode strings and encode with
  `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
- `serialize()` converts booleans to `1`/`0`.

## CastApplier

`OrmUniversal\Relational\Type\CastApplier` applies Laravel-style cast definitions
to raw rows without depending on Laravel:

```php
$builder = OrmUniversal::table('sellers')->castWith([
    'is_active' => 'bool',
    'amount' => 'decimal:2',
    'metadata' => 'array',
    'status' => StatusEnum::class,
]);
```

- **Decimals always string** (`decimal:n`, `numeric`): they are never converted to float.
- JSON-like (`array`, `json`, `collection`, `object`) decode to array/stdClass.
- Backed enums resolve with `Enum::from($value)`; `uuid` normalizes to lowercase.
- `date`/`datetime` return `DateTimeImmutable`; `vector` returns `list<float>`.
- Unknown casts leave the value intact; `null` always passes as `null`.

`applyRow()`/`applyMany()` run before hydration in `get()` and
`first()`.

See [Schema](schema.md), [Migrations](migrations.md) and [Dialects](dialects.md).
