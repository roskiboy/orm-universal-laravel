<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Engine;

use OrmUniversal\Contracts\EntityInterface;
use OrmUniversal\Contracts\Enums\ComparisonOperator;
use OrmUniversal\Contracts\Enums\HydrationMode;
use OrmUniversal\Contracts\Enums\PortabilityClass;
use OrmUniversal\Contracts\Exceptions\BusinessOperationException;
use OrmUniversal\Contracts\Exceptions\CompilationException;
use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\Comparison;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\Value;
use OrmUniversal\Relational\Business\AbstractSpecification;
use OrmUniversal\Relational\Business\BusinessMethodResolver;
use OrmUniversal\Relational\Business\BusinessRegistry;
use OrmUniversal\Relational\Context\QueryContext;
use OrmUniversal\Relational\Context\RequestContext;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Execution\BatchPlanner;
use OrmUniversal\Relational\Execution\DestructiveStatementPolicy;
use OrmUniversal\Relational\Hydration\Hydrator;
use OrmUniversal\Relational\Optimizer\Normalizer;
use OrmUniversal\Relational\Optimizer\Pass\ProjectionPruningPass;
use OrmUniversal\Relational\Optimizer\PerformanceAnalyzer;
use OrmUniversal\Relational\Optimizer\PortabilityAnalyzer;
use OrmUniversal\Relational\Optimizer\Validator;
use OrmUniversal\Relational\Recordset\EloquentRecordset;
use OrmUniversal\Relational\Recordset\EntityRecordset;
use OrmUniversal\Relational\Recordset\ObjectRecordset;
use OrmUniversal\Relational\Relation\RelationCollection;
use OrmUniversal\Relational\Relation\RelationDefinition;
use OrmUniversal\Relational\Relation\RelationHydrator;
use OrmUniversal\Relational\Relation\RelationOptimizer;
use OrmUniversal\Relational\Relation\RelationRegistry;
use OrmUniversal\Relational\Type\TypeCaster;
use OrmUniversal\Relational\Type\TypeRegistry;
use OrmUniversal\Relational\Type\TypeResolver;
use OrmUniversal\Relational\Type\TypeSerializer;
use PHPUnit\Framework\TestCase;

final class EngineExtensionsTest extends TestCase
{
    public function test_query_context_is_immutable(): void
    {
        $context = QueryContext::make('mysql', tenant: 'acme');
        $updated = $context->withTenant('other')->withOption('trace', true);

        self::assertSame('acme', $context->tenant());
        self::assertSame('other', $updated->tenant());
        self::assertTrue($updated->option('trace'));
        self::assertNull($context->option('trace'));
    }

    public function test_request_context_is_explicit(): void
    {
        $context = RequestContext::generate();

        self::assertStringStartsWith('req_', $context->requestId());
        self::assertArrayHasKey('request_id', $context->toArray());
    }

    public function test_business_resolver_caches_and_traces(): void
    {
        $registry = new BusinessRegistry;
        $registry->registerScope('paid', fn (object $query): object => $query, 'Order');
        $registry->registerOperation('archive', fn (object $query): string => 'archived', 'Order');

        $resolver = new BusinessMethodResolver($registry);

        $query = new class
        {
            public bool $scoped = false;
        };

        self::assertTrue($resolver->supportsScope('paid', 'Order'));
        self::assertTrue($resolver->supportsScope('paid', 'Order'));
        self::assertFalse($resolver->supportsScope('unknown', 'Order'));
        self::assertTrue($resolver->apply('paid', $query, 'Order'));
        self::assertTrue($resolver->apply('archive', $query, 'Order'));

        $stats = $resolver->cache()->stats();
        self::assertGreaterThan(0, $stats['hits']);
        self::assertNotNull($resolver->lastTrace());
    }

    public function test_business_scope_ambiguity_throws_descriptively(): void
    {
        $registry = new BusinessRegistry;
        $registry->registerScope('active', fn (object $q): object => $q, 'Customer');
        $registry->registerScope('active', fn (object $q): object => $q, 'Seller');

        $this->expectException(BusinessOperationException::class);
        $registry->scope('active');
    }

    public function test_specifications_compose(): void
    {
        $spec = new class extends AbstractSpecification
        {
            public function apply(object $query): void
            {
                $query->where('active', true);
            }
        };

        $composite = $spec->and($spec);

        self::assertSame('and', $composite->operator());
    }

    public function test_relation_definitions_and_registry(): void
    {
        $registry = new RelationRegistry;
        $registry->register('Order', RelationDefinition::hasMany('items', TableIdentifier::fromString('order_items'), 'order_id'));
        $registry->register('Order', RelationDefinition::belongsTo('customer', TableIdentifier::fromString('customers'), 'customer_id'));

        self::assertTrue($registry->has('Order', 'items'));
        self::assertSame(['items', 'customer'], $registry->names('Order'));
        self::assertTrue($registry->get('Order', 'items')->isToMany());
        self::assertFalse($registry->get('Order', 'customer')->isToMany());

        $graph = $registry->graph('Order');
        self::assertContains('items', $graph->paths());
    }

    public function test_relation_optimizer_plans(): void
    {
        $optimizer = new RelationOptimizer;
        $dialect = SqliteDialect::make('3.49.2');
        $toMany = RelationDefinition::hasMany('items', TableIdentifier::fromString('order_items'), 'order_id')->toMetadata();
        $toOne = RelationDefinition::belongsTo('customer', TableIdentifier::fromString('customers'), 'customer_id')->toMetadata();

        self::assertSame('single_query', $optimizer->plan($toMany, $dialect, 1)['strategy']);
        self::assertSame('join', $optimizer->plan($toOne, $dialect, 500)['strategy']);
        self::assertSame('batch_in', $optimizer->plan($toMany, $dialect, 500)['strategy']);
        self::assertSame('chunked_in', $optimizer->plan($toMany, $dialect, 40000)['strategy']);
    }

    public function test_relation_hydrator_and_collection(): void
    {
        $relation = RelationDefinition::hasMany('items', TableIdentifier::fromString('order_items'), 'order_id')->toMetadata();
        $hydrator = new RelationHydrator;

        $parents = [
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
        ];

        $hydrated = $hydrator->hydrate($parents, $relation, [
            '1' => [['id' => 10, 'order_id' => 1], ['id' => 11, 'order_id' => 1]],
        ]);

        self::assertInstanceOf(RelationCollection::class, $hydrated[0]['items']);
        self::assertSame(2, $hydrated[0]['items']->count());
        self::assertSame([10, 11], $hydrated[0]['items']->pluck('id'));
        self::assertTrue($hydrated[1]['items']->isEmpty());
    }

    public function test_recordsets_object_entity_and_factory(): void
    {
        $rows = [['id' => 1, 'name' => 'ana'], ['id' => 2, 'name' => 'bob']];

        $objects = ObjectRecordset::fromRows($rows);
        self::assertSame('ana', $objects->first()->name);

        $entities = EntityRecordset::fromRows(EngineExtensionUser::class, $rows);
        self::assertInstanceOf(EngineExtensionUser::class, $entities->first());
        self::assertSame('bob', $entities->last()->name);

        $models = EloquentRecordset::fromRows(static fn (array $row): object => (object) $row, $rows);
        self::assertSame(2, $models->count());
    }

    public function test_optimizer_pipeline_normalize_validate_prune(): void
    {
        $normalizer = new Normalizer;
        $expression = (new Comparison(new Column('a'), ComparisonOperator::Eq, new Value(1)))
            ->and((new Comparison(new Column('b'), ComparisonOperator::Eq, new Value(2))));

        $normalized = $normalizer->normalizeExpression($expression);

        self::assertInstanceOf(LogicalExpression::class, $normalized);

        $query = SelectQuery::make('users')->select(Column::make('id'), Column::make('id'));
        $pruned = (new ProjectionPruningPass)->apply($query);

        self::assertInstanceOf(SelectQuery::class, $pruned);
        self::assertSame(1, count($pruned->columns()));

        $this->expectException(CompilationException::class);
        (new Validator)->validate(new SelectQuery);
    }

    public function test_validator_warnings_for_unbounded_writes(): void
    {
        $warnings = (new Validator)->warnings(SelectQuery::make('users'));

        self::assertSame([], $warnings);
    }

    public function test_destructive_policy_denies_without_where(): void
    {
        $policy = new DestructiveStatementPolicy(DestructiveStatementPolicy::DENY);

        $policy->assertAllowed('UPDATE users', true);
        $this->expectException(QueryException::class);
        $policy->assertAllowed('DELETE users', false);
    }

    public function test_destructive_policy_confirmation(): void
    {
        $policy = new DestructiveStatementPolicy(DestructiveStatementPolicy::REQUIRE_CONFIRMATION);

        $policy->assertAllowed('DELETE users', false, true);

        $this->expectException(QueryException::class);
        $policy->assertAllowed('DELETE users', false);
    }

    public function test_batch_planner_respects_placeholder_budget(): void
    {
        $planner = new BatchPlanner(SqliteDialect::make('3.49.2'));

        self::assertSame(16383, $planner->maxRowsPerBatch(1));
        self::assertSame(8191, $planner->maxRowsPerBatch(2));

        $rows = array_fill(0, 16384, ['a' => 1, 'b' => 2]);
        self::assertCount(3, $planner->chunkRows($rows, 2));
        self::assertCount(3, $planner->chunkValues(range(1, 40000)));
        self::assertSame([], $planner->chunkRows([], 1));
    }

    public function test_portability_and_performance_reports(): void
    {
        $query = SelectQuery::make('events')->select('id')->where(Expression::json(Column::make('meta'), '$.type', true), '=', 'login');

        $portability = (new PortabilityAnalyzer)->analyze($query);
        self::assertSame(PortabilityClass::PartiallyPortable, $portability->classification());
        self::assertLessThan(100.0, $portability->percent());
        self::assertNotEmpty($portability->toArray()['differences']);

        $performance = (new PerformanceAnalyzer)->analyze($query, HydrationMode::Light);
        self::assertSame('low', $performance->estimatedComplexity);
        self::assertSame('low', $performance->hydrationCost);
        self::assertTrue($performance->streamingAvailable);
    }

    public function test_type_resolver_caster_and_serializer(): void
    {
        $registry = TypeRegistry::withDefaults();
        $resolver = new TypeResolver($registry);

        $uuid = $resolver->require('uniqueidentifier');
        self::assertSame('uuid', $uuid->name());

        $jsonb = $resolver->require('jsonb');
        self::assertSame('jsonb', $jsonb->name());

        $caster = new TypeCaster;
        self::assertSame(5, $caster->cast('5', $registry->get('integer')));

        $serializer = new TypeSerializer;
        self::assertSame('{"a":1}', $serializer->serialize(['a' => 1], $registry->get('json')));
    }

    public function test_hydration_modes_via_generic_hydrator(): void
    {
        self::assertSame(HydrationMode::Zero, Hydrator::zero()->mode());
        self::assertSame(HydrationMode::Light, Hydrator::light()->mode());
        self::assertSame(HydrationMode::Entity, Hydrator::entity(EngineExtensionUser::class)->mode());
        self::assertSame(HydrationMode::Eloquent, Hydrator::factory(static fn (array $row): object => (object) $row)->mode());
    }
}

final class EngineExtensionUser implements EntityInterface
{
    public ?int $id = null;

    public ?string $name = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function attributes(): array
    {
        return $this->attributes;
    }

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

    public function exists(): bool
    {
        return $this->id !== null;
    }
}
