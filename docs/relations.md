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
- `MorphTo` no se traduce: usa `custom()` si necesitas modelarla.
- `HasOneThrough`/`HasManyThrough` existen como constantes y fábrica genérica, pero el
  adaptador no las infiere desde Eloquent en esta versión.
- El adaptador no ejecuta `load()` ni altera el modelo: solo describe.
