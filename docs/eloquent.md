# Eloquent — orm-universal/laravel

La integración es aditiva: **Eloquent no se modifica ni se envuelve**. El motor universal
convive con él en modo híbrido.

## Macros globales

El provider registra dos macros en `Illuminate\Database\Eloquent\Builder`:

```php
Builder::macro('universal', function () {
    return OrmUniversal::table($this->getModel()->getTable(), $this->getModel()->getConnectionName());
});

Builder::macro('recordset', function () {
    return OrmUniversal::table($this->getModel()->getTable(), $this->getModel()->getConnectionName())
        ->recordset();
});
```

Uso:

```php
Seller::query()->universal()->where('is_active', true)->get();
Seller::query()->recordset();   // RecordsetInterface de toda la tabla
```

Los macros no inyectan filtros ni alteran la consulta Eloquent original.

## Trait `HasUniversalQueries`

Trait opcional (`OrmUniversal\Laravel\Concerns\HasUniversalQueries`) para exponer el
builder universal desde el modelo:

| Método | Descripción |
|---|---|
| `universal(): QueryBuilder` | Builder UDE sobre tabla/conexión del modelo |
| `universalTable(): string` | Resuelve `getTable()` sin tocar la instancia real |
| `universalConnection(): string` | `getConnectionName()` o `database.default` |
| `universalRecordset(): RecordsetInterface` | Recordset de toda la tabla |

```php
use OrmUniversal\Laravel\Concerns\HasUniversalQueries;

class Seller extends Model
{
    use HasUniversalQueries;
}

Seller::universal()->where('is_active', true)->recordset();
(new Seller)->universalRecordset();
```

## `LaravelBuilderAdapter`

Puente unidireccional desde un `Eloquent\Builder` hacia un `QueryBuilder` de UDE
(`src/Adapters/LaravelBuilderAdapter.php`):

- `fromEloquent(Builder $builder, QueryExecutor $executor): QueryBuilder`
- `recordset(Builder $builder, QueryExecutor $executor): RecordsetInterface`

Copia **solo** las cláusulas que entiende:

| Cláusula Eloquent | Traducción UDE |
|---|---|
| Basic | `where` / `orWhere` con operador |
| Null / NotNull | `whereNull` / `whereNotNull` |
| In (y not) | `whereIn` / `whereNotIn` |
| Between | `whereBetween` |
| Column | `whereColumn` |
| orders | `orderBy` |
| limit / offset | `limit` / `offset` |

Las cláusulas desconocidas se **omiten, nunca se adivinan**, y la documentación del
adaptador advierte: úsalo solo si las cláusulas copiadas cubren la consulta completa.

## Garantías del modo híbrido

`LaravelCompatibilityLayer::report()` declara los invariantes:

- Resultados de Eloquent sin cambios.
- Ordenación, semántica de `null`, transacciones, casting y eventos sin cambios.

No hay interceptores globales: si no llamas a `universal()`, `recordset()` ni a la
fachada, el comportamiento es el de Laravel puro.

## Cuándo usar cada uno

- Eloquent vanilla: modelos, eventos, relaciones, casting y todo el ecosistema Laravel.
- UDE: consultas de lectura intensiva, recordsets/cursor, análisis de schema y
  portabilidad entre motores.
