# Extending

The package is extended through explicit registries; there is no need to touch the core.
For packaged contributions use the [plugin system](plugin-system.md).

## Registering or replacing a dialect

`DialectRegistry::register(DialectVendor $vendor, callable $factory, bool $override = false)`
receives a `callable(DialectVersion): AbstractDialect` factory. `override: true`
replaces the existing line (for example, for a fork with its own capabilities).

```php
use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Dialect\Capabilities\PostgreSqlCapabilities;
use OrmUniversal\Relational\Dialect\DialectRegistry;

$registry = DialectRegistry::withDefaults();

$registry->register(
    DialectVendor::Postgresql,
    static fn (DialectVersion $version): AbstractDialect => new class($version, PostgreSqlCapabilities::for($version)) extends AbstractDialect {
        public function name(): string { return 'pgsql-custom-'.$this->version->numeric(); }
        public function vendor(): DialectVendor { return DialectVendor::Postgresql; }
        public function driverName(): string { return 'pgsql'; }
    },
    override: true,
);
```

An engine that does not exist in `DialectVendor` requires adding its case in the
`OrmUniversal\Contracts` package; the enum is closed and the core does not guess vendors.

`DialectResolver::resolve($driver, $rawVersion, $registry)` accepts the custom
registry, useful in tests and multi-connection setups.

## Registering a type

Implement `TypeInterface` or use `UniversalType`, and register it in
`TypeRegistry`:

```php
use OrmUniversal\Contracts\Enums\TypeKind;
use OrmUniversal\Relational\Type\TypeRegistry;
use OrmUniversal\Relational\Type\UniversalType;

$registry = TypeRegistry::withDefaults();
$registry->register(new UniversalType('money', TypeKind::Decimal, ['precision' => 19, 'scale' => 4]));
```

`register(TypeInterface $type, bool $override = false)` throws `DialectException`
if the name already exists without override. `TypeResolver` will keep resolving aliases
to the registered types. See [Types](types.md).

## Registering an optimizer pass

Implement `OrmUniversal\Contracts\OptimizerPassInterface`:

```php
use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Optimizer\Optimizer;

final class StripNoopLimitPass implements OptimizerPassInterface
{
    public function name(): string { return 'strip-noop-limit'; }

    public function apply(AstNodeInterface $node): AstNodeInterface
    {
        return $node; // your safe transformation
    }
}

$optimizer = new Optimizer;
$optimizer->addPass(new StripNoopLimitPass);
```

Non-negotiable rule: a pass cannot change the query result. See
[Optimizer](optimizer.md).

## Registering business logic

```php
use OrmUniversal\Relational\Business\BusinessRegistry;

$registry = new BusinessRegistry;
$registry->registerScope('paid', fn (object $query): object => $query->where('status', 'paid'), 'Order');
$registry->registerSpecification('highValue', HighValueSpecification::class);
$registry->registerOperation('archive', fn (object $query): string => 'archived', 'Order');
```

`BusinessMethodResolver` resolves scopes first and then operations, with
`MethodCache` and traces (`lastTrace()`, `report()`). See
[Business Logic](business-logic.md).

## Registering relations

```php
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Relation\RelationDefinition;
use OrmUniversal\Relational\Relation\RelationRegistry;

$relations = new RelationRegistry;
$relations->register('Order', RelationDefinition::hasMany('items', TableIdentifier::fromString('order_items'), 'order_id'));
$relations->register('Order', RelationDefinition::belongsTo('customer', TableIdentifier::fromString('customers'), 'customer_id'));
```

`graph()` generates the eager loading tree; `RelationOptimizer` decides
join/batch/subquery. See [Relations](relations.md).

## Registering plugins

`PluginRegistry` groups all of the above so a package can apply it in a
kernel:

```php
use OrmUniversal\Relational\Plugin\PluginRegistry;

$plugins = new PluginRegistry;
$plugins->register(new MyPlugin);
$plugins->registerAll($kernel);
$plugins->bootAll($kernel);
```

Contract in [Plugins](plugin-system.md).

## Checklist

- [ ] The dialect declares real `limitations()` and its per-version capabilities.
- [ ] The pass does not alter semantics and has a stable `name()`.
- [ ] Scope/type/plugin names are unique (no implicit override).
- [ ] Document alternatives in `UnsupportedFeatureException` when applicable.
- [ ] Add tests: [Testing](testing.md).
