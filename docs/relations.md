# Relations — orm-universal/laravel

`LaravelModelAdapter` reads metadata and relations from an Eloquent model **without
modifying it** and converts them into first-class `RelationDefinition`s.

## `LaravelModelAdapter`

`src/Adapters/LaravelModelAdapter.php`.

### `metadata(string $modelClass): array`

Returns, without instantiating the model constructor:

`class`, `table`, `connection`, `primary_key`, `incrementing`, `timestamps`,
`soft_deletes` (existence of `trashed()`), `casts`, `fillable`, `guarded`, `hidden`,
`appends`.

```php
$adapter = new LaravelModelAdapter();
$meta = $adapter->metadata(App\Models\Seller::class);
```

### `relationDefinitions(string $modelClass): list<RelationDefinition>`

- Walks public methods without required parameters (excludes static and `__*` ones).
- Invokes each method inside `try/catch`; if it does not return a `Relation`, it is ignored.
- Maps the supported relations; `MorphTo` is intentionally left out because the related
  model depends on the row and is not guessed.

### `universal(string $modelClass, QueryExecutor $executor): QueryBuilder`

UDE builder for the model's table.

## Mapped relations

| Eloquent relation | `RelationDefinition` | Data |
|---|---|---|
| `BelongsTo` | `belongsTo()` | FK, owner key |
| `HasOne` | `hasOne()` | FK, local key |
| `HasMany` | `hasMany()` | FK, local key |
| `BelongsToMany` | `belongsToMany()` | pivot table, pivot keys |
| `MorphOne` | `morphOne()` | FK, morph type, local key |
| `MorphMany` | `morphMany()` | FK, morph type, local key |

Each definition is enriched with `withMetadata(['model' => ...])` indicating the related
model.

## `RelationDefinition`

`OrmUniversal\Relational\Relation\RelationDefinition` (`packages/relational`).

Types available as constants: `HAS_ONE`, `HAS_MANY`, `BELONGS_TO`, `BELONGS_TO_MANY`,
`HAS_ONE_THROUGH`, `HAS_MANY_THROUGH`, `MORPH_ONE`, `MORPH_MANY`, `MORPH_TO`,
`MORPH_TO_MANY`, `MORPHED_BY_MANY`, `CUSTOM`.

Factories: `hasOne()`, `hasMany()`, `belongsTo()`, `belongsToMany()`, `morphOne()`,
`morphMany()`, `custom()`, `fromMetadata()`.

Accessors: `name()`, `type()`, `relatedTable()`, `foreignKey()`, `localKey()`,
`isToMany()`, `pivotTable()`, `pivotColumns()`, `morphType()`, `usesPivot()`,
`metadata()`, `toArray()`, `toMetadata()`.

```php
foreach ($adapter->relationDefinitions(App\Models\Seller::class) as $relation) {
    $relation->name();          // 'orders'
    $relation->type();          // 'hasMany'
    $relation->relatedTable()->qualifiedName();
    $relation->foreignKey();
    $relation->isToMany();
}
```

## Honest limitations

- Relations are discovered by Eloquent convention (methods returning a `Relation`);
  dynamic definitions built at runtime are not detected.
- `MorphTo` is only translated with an explicit morph map; without it, it is not guessed.
- The adapter does not run `load()` or alter the model: it only describes.

## Relation aggregates and parity

`LaravelModelAdapter::relationDefinitions()` builds the 11 families (hasOne/hasMany,
belongsTo, belongsToMany, through, morph*, morphedByMany). With a `RelationRegistry` you
can use UDE aggregates equivalent to Eloquent:

```php
use OrmUniversal\Relational\Relation\RelationRegistry;

$registry = new RelationRegistry();
foreach ((new LaravelModelAdapter)->relationDefinitions(Seller::class) as $definition) {
    $registry->register('sellers', $definition);
}

$rows = OrmUniversal::table('sellers')
    ->usingRelations($registry)
    ->withCount('treasuryMovements')          // alias treasury_movements_count
    ->withSum('treasuryMovements', 'amount')  // alias treasury_movements_sum_amount
    ->orderBy('id')
    ->get();
```

Parity verified row by row against Eloquent's `withCount`/`withSum` on SQLite
(`tests/Feature/Ude/RelationParityTest.php`), including native paginators
(`LaravelPaginatorAdapter`) and the 11 families in the Core suite
(`RelationFamilyTest`, `RelationAggregateTest`).
