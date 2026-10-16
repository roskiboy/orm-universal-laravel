# Relations

The relation engine is first-class: nothing degrades to strings. All the
pieces live in `OrmUniversal\Relational\Relation`.

## RelationMetadata and RelationDefinition

`RelationMetadata` is the immutable descriptor: `name()`, `type()`,
`relatedTable()` (`TableIdentifier`), `foreignKey()`, `localKey()`,
`isToMany()`, `metadata()`.

`RelationDefinition` implements the same interface
(`RelationDefinitionInterface`) and adds per-family factories:

```php
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Relation\RelationDefinition;

RelationDefinition::hasOne('profile', TableIdentifier::fromString('profiles'), 'user_id');
RelationDefinition::hasMany('orders', TableIdentifier::fromString('orders'), 'user_id');
RelationDefinition::belongsTo('customer', TableIdentifier::fromString('customers'), 'customer_id');
RelationDefinition::belongsToMany(
    'roles',
    TableIdentifier::fromString('roles'),
    TableIdentifier::fromString('role_user'),
    foreignKey: 'user_id',
    relatedKey: 'role_id',
    pivotColumns: ['created_at'],
);
RelationDefinition::morphOne(...);
RelationDefinition::morphMany(...);
RelationDefinition::custom('audit', $table, 'user_id', 'id', toMany: true);
```

Constants: `HAS_ONE`, `HAS_MANY`, `BELONGS_TO`, `BELONGS_TO_MANY`,
`HAS_ONE_THROUGH`, `HAS_MANY_THROUGH`, `MORPH_ONE`, `MORPH_MANY`, `MORPH_TO`,
`MORPH_TO_MANY`, `MORPHED_BY_MANY`, `CUSTOM`. Utilities: `withMetadata()`,
`pivotTable()`, `pivotColumns()`, `morphType()`, `usesPivot()`, `toArray()`,
`toMetadata()`.

## RelationQuery

Builds the query for the related table as a `QueryBuilder`:

```php
use OrmUniversal\Relational\Relation\RelationQuery;

$relationQuery = new RelationQuery($executor);

$builder = $relationQuery->for($definition, $userId);          // one parent
$batch   = $relationQuery->batch($definition, [1, 2, 3]);      // one IN, no N+1
```

`batch()` requires at least one key (`RelationException`) and uses
`whereIn($foreignKey, ...)`; with a pivot it generates an explicit `join`.

## RelationBatchLoader and RelationHydrator

`RelationBatchLoader::load(iterable $parents, RelationMetadata $relation, callable $builderFactory): array`
groups the rows by local key. `RelationHydrator::hydrate()` (or `attach()`)
attaches the result to each parent: arrays, `EntityInterface` or plain objects. To-many
relations are wrapped in a `RelationCollection`.

```php
use OrmUniversal\Relational\Relation\RelationHydrator;

$grouped = (new RelationBatchLoader)->load($parents, $relation, fn ($table) =>
    QueryBuilder::table($executor, $table)
);
$parents = (new RelationHydrator)->hydrate($parents, $relation, $grouped);
```

`RelationCollection` offers `name()`, `all()`, `first()`, `last()`, `count()`,
`isEmpty()`, `groupBy(string $field)`, `pluck(string $field)` and is iterable/JSON.

## RelationOptimizer

`plan(RelationMetadata $relation, AbstractDialect $dialect, int $parentCount = 1, bool $parentHasWhere = false, bool $needsRelatedFilter = false)`
returns `['strategy' => ..., 'reason' => ..., 'batch_size' => ...]`:

| Strategy | When |
|---|---|
| `single_query` | a single parent |
| `join` | to-one without filters on the related side |
| `batch_in` | to-many within the placeholder budget |
| `chunked_in` | the parent set exceeds `MAX_PLACEHOLDERS` |
| `subquery` | related-side filter over already-filtered parents |

## RelationGraph and RelationRegistry

`RelationGraph::add('orders.products.category', $metadata)` builds the eager loading
tree; `paths()` returns the dotted paths in order.
`RelationRegistry` registers definitions per model or table:

```php
use OrmUniversal\Relational\Relation\RelationRegistry;

$registry = new RelationRegistry;
$registry->register('Order', $definition);
$registry->has('Order', 'items');
$registry->get('Order', 'items');
$registry->names('Order');
$graph = $registry->graph('Order');
```

## N1Detector

Observes repeated loads with multiple parents and reacts according to
`OrmUniversal\Contracts\Enums\NPlusOneMode`:

| Mode | Reaction |
|---|---|
| `Ignore` | does nothing |
| `Warning` | `trigger_error(..., E_USER_WARNING)` |
| `Log` | `$logger->warning()` |
| `Telemetry` | `$logger->info('[ude.n+1] ...')` |
| `Exception` | throws `RelationException` |

```php
$detector = new N1Detector(NPlusOneMode::Warning, $logger, threshold: 2);
$detector->record('orders.items', parentCount: 100);
$detector->warnings();
```

## RelationResolver

Infers metadata from foreign keys **only with unambiguous evidence**:
`belongsToMany(string $localTable, array $foreignKeys)` produces
`belongsTo` relations from FKs of the local table, and
`hasMany(string $localTable, string $referencingTable, array $foreignKeys)`
produces `hasMany` from FKs that point to the local table. Every resulting
`RelationMetadata` marks `inferred_from = foreign_key`.

See [Schema](schema.md), [Query Engine](query-engine.md) and
[Optimizer](optimizer.md).
