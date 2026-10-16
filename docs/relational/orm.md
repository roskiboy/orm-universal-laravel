# ORM and hydration

Hydration converts associative rows into PHP values. There is a single
real implementation: `OrmUniversal\Relational\Hydration\Hydrator`, which covers the
four modes documented in `OrmUniversal\Contracts\Enums\HydrationMode`:

| Mode | Enum | Result |
|---|---|---|
| zero | `HydrationMode::Zero` | associative array as is |
| light | `HydrationMode::Light` | `stdClass` per row |
| entity | `HydrationMode::Entity` | instance of a class |
| factory | `HydrationMode::Eloquent` | object returned by your `callable` |

## Construction

```php
use OrmUniversal\Relational\Hydration\Hydrator;

Hydrator::zero();                         // Zero mode
Hydrator::light();                        // Light mode
Hydrator::entity(UserEntity::class);      // Entity mode
Hydrator::factory(static fn (array $row): object => (object) $row);

// Direct constructor:
new Hydrator($mode, $entityClass = null, $factory = null);
```

`Hydrator` implements `HydratorInterface`:

- `mode(): HydrationMode`
- `hydrate(array $row): mixed`
- `hydrateMany(iterable $rows): iterable` — generator per row.

## Entity mode

The target class must exist; otherwise
`OrmUniversal\Contracts\Exceptions\HydrationException`. The hydrator instantiates
**without a constructor** (`ReflectionClass::newInstanceWithoutConstructor()`):

- If it implements `EntityInterface`, it assigns each column with `setAttribute()`.
- Otherwise, it writes existing properties via reflection and creates dynamic
  properties when the column does not exist.

`EntityInterface` (`OrmUniversal\Contracts\EntityInterface`) requires
`attributes()`, `getAttribute(string)`, `setAttribute(string, mixed): static` and
`exists(): bool`.

```php
use OrmUniversal\Contracts\EntityInterface;

final class UserEntity implements EntityInterface
{
    public ?int $id = null;
    public ?string $name = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function attributes(): array { return $this->attributes; }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? $this->{$name} ?? null;
    }

    public function setAttribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }

        return $this;
    }

    public function exists(): bool { return $this->id !== null; }
}
```

## Factory mode (Eloquent and others)

`Hydrator::factory(callable $factory)` receives
`callable(array<string, mixed>): object`. The core **does not depend on Laravel**; the
`OrmUniversal\Laravel` adapter provides the callable that builds the model.

```php
$hydrator = Hydrator::factory(static fn (array $row): object => User::hydrate([$row]));
```

`EloquentRecordset::fromRows($factory, $rows)` applies the same pattern to a
complete result.

## Integration with QueryBuilder

```php
$builder->zeroHydration();          // Hydrator::zero()
$builder->light();                  // Hydrator::light()
$builder->entity(UserEntity::class);
$builder->models($factory);         // Hydrator::factory($factory)
$builder->eloquent($factory);       // alias of models()
$builder->hydrateWith($hydrator);   // any HydratorInterface
```

`QueryBuilder::get()` hydrates with `hydrateMany()`; `cursor()` passes the hydrator to the
`StreamingRecordset`; `pluck()`/`value()` read attributes from arrays, entities
(`getAttribute`) or objects (`{$attribute}`).

See [Recordsets](recordset.md), [Relations](relations.md) and
[Business Logic](business-logic.md).
