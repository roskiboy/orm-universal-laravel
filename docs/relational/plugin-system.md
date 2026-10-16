# Plugin system

Plugins package contributions (dialects, types, optimizers, business logic,
adapters) without the core depending on them.

## PluginInterface

`OrmUniversal\Contracts\PluginInterface`:

```php
namespace OrmUniversal\Contracts;

interface PluginInterface
{
    public function name(): string;

    public function register(object $kernel): void;

    public function boot(object $kernel): void;
}
```

- `name()` is the unique key in the registry.
- `register(object $kernel)` receives the host's container/registrar and
  must **only** register (not resolve or execute).
- `boot(object $kernel)` completes initialization once all are registered.

## Example

```php
use OrmUniversal\Contracts\PluginInterface;
use OrmUniversal\Relational\Type\TypeRegistry;
use OrmUniversal\Relational\Type\UniversalType;
use OrmUniversal\Contracts\Enums\TypeKind;

final class MoneyTypePlugin implements PluginInterface
{
    public function name(): string
    {
        return 'money-type';
    }

    public function register(object $kernel): void
    {
        /** @var TypeRegistry $types */
        $types = $kernel->types();
        $types->register(new UniversalType('money', TypeKind::Decimal, ['precision' => 19, 'scale' => 4]));
    }

    public function boot(object $kernel): void
    {
        // Optional: validations or warmup.
    }
}
```

`$kernel` is deliberately flexible as an `object`: each host exposes its own
registries. In this package, the real registries are `DialectRegistry`,
`TypeRegistry`, `RelationRegistry`, `BusinessRegistry` and `Optimizer`.

## PluginRegistry

`OrmUniversal\Relational\Plugin\PluginRegistry`:

```php
use OrmUniversal\Relational\Plugin\PluginRegistry;

$plugins = new PluginRegistry;
$plugins->register(new MoneyTypePlugin);           // duplicate without override throws InvalidArgumentException
$plugins->register(new MoneyTypePlugin, override: true);

$plugins->has('money-type');
$plugins->get('money-type');
$plugins->names();
$plugins->registerAll($kernel);
$plugins->bootAll($kernel);                        // only once per plugin
$plugins->booted();
```

`registerAll()` iterates in registration order; `bootAll()` skips those already booted
(useful in workers that restart partially). See [Extending](extending.md) for
the concrete registration points.
