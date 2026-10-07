# Relaciones — orm-universal/laravel

`LaravelModelAdapter` lee metadatos y relaciones de un modelo Eloquent **sin modificarlo**
y las convierte en `RelationDefinition` de primera clase.

## `LaravelModelAdapter`

`src/Adapters/LaravelModelAdapter.php`.

### `metadata(string $modelClass): array`

Devuelve, sin instanciar el constructor del modelo:

`class`, `table`, `connection`, `primary_key`, `incrementing`, `timestamps`,
`soft_deletes` (existencia de `trashed()`), `casts`, `fillable`, `guarded`, `hidden`,
`appends`.

```php
$adapter = new LaravelModelAdapter();
$meta = $adapter->metadata(App\Models\Seller::class);
```

### `relationDefinitions(string $modelClass): list<RelationDefinition>`

- Recorre los métodos públicos sin parámetros requeridos (excluye estáticos y `__*`).
- Invoca cada método dentro de `try/catch`; si no devuelve una `Relation`, lo ignora.
- Mapea las relaciones soportadas; `MorphTo` queda fuera a propósito porque el modelo
  relacionado depende de la fila y no se adivina.

### `universal(string $modelClass, QueryExecutor $executor): QueryBuilder`

Builder UDE para la tabla del modelo.

## Relaciones mapeadas

| Relación Eloquent | `RelationDefinition` | Datos |
|---|---|---|
| `BelongsTo` | `belongsTo()` | FK, owner key |
| `HasOne` | `hasOne()` | FK, local key |
| `HasMany` | `hasMany()` | FK, local key |
| `BelongsToMany` | `belongsToMany()` | tabla pivote, claves pivote |
| `MorphOne` | `morphOne()` | FK, morph type, local key |
| `MorphMany` | `morphMany()` | FK, morph type, local key |

Cada definición se enriquece con `withMetadata(['model' => ...])` indicando el modelo
relacionado.

## `RelationDefinition`

`OrmUniversal\Relational\Relation\RelationDefinition` (`packages/relational`).

Tipos disponibles como constantes: `HAS_ONE`, `HAS_MANY`, `BELONGS_TO`, `BELONGS_TO_MANY`,
`HAS_ONE_THROUGH`, `HAS_MANY_THROUGH`, `MORPH_ONE`, `MORPH_MANY`, `MORPH_TO`,
`MORPH_TO_MANY`, `MORPHED_BY_MANY`, `CUSTOM`.

Fábricas: `hasOne()`, `hasMany()`, `belongsTo()`, `belongsToMany()`, `morphOne()`,
`morphMany()`, `custom()`, `fromMetadata()`.

Accesores: `name()`, `type()`, `relatedTable()`, `foreignKey()`, `localKey()`,
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

## Limitaciones honestas

- Las relaciones se descubren por convención de Eloquent (métodos que devuelven
  `Relation`); las definiciones dinámicas construidas en runtime no se detectan.
- `MorphTo` solo se traduce con morph map explícito; sin él no se adivina.
- El adaptador no ejecuta `load()` ni altera el modelo: solo describe.

## Agregados de relación y paridad

`LaravelModelAdapter::relationDefinitions()` construye las 11 familias (hasOne/hasMany,
belongsTo, belongsToMany, through, morph*, morphedByMany). Con un `RelationRegistry`
puedes usar agregados UDE equivalentes a Eloquent:

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

Paridad verificada fila a fila contra `withCount`/`withSum` de Eloquent en SQLite
(`tests/Feature/Ude/RelationParityTest.php`), incluidos paginadores nativos
(`LaravelPaginatorAdapter`) y las 11 familias en la suite del Core
(`RelationFamilyTest`, `RelationAggregateTest`).
