<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Relation\RelationDefinition;
use ReflectionClass;

/**
 * Reads Eloquent model metadata and relation definitions without modifying the
 * model. Relations become first class RelationDefinition objects.
 *
 * @api public
 */
final class LaravelModelAdapter
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    public function metadata(string $modelClass): array
    {
        $this->assertModel($modelClass);

        /** @var Model $model */
        $model = (new ReflectionClass($modelClass))->newInstanceWithoutConstructor();

        return [
            'class' => $modelClass,
            'table' => $model->getTable(),
            'connection' => $model->getConnectionName(),
            'primary_key' => $model->getKeyName(),
            'incrementing' => $model->getIncrementing(),
            'timestamps' => $model->usesTimestamps(),
            'soft_deletes' => method_exists($model, 'trashed'),
            'casts' => $model->getCasts(),
            'fillable' => $model->getFillable(),
            'guarded' => $model->getGuarded(),
            'hidden' => $model->getHidden(),
            'appends' => array_values($model->getAppends()),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<RelationDefinition>
     */
    public function relationDefinitions(string $modelClass): array
    {
        $this->assertModel($modelClass);

        /** @var Model $model */
        $model = (new ReflectionClass($modelClass))->newInstanceWithoutConstructor();
        $definitions = [];

        foreach ((new ReflectionClass($modelClass))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0 || str_starts_with($method->getName(), '__')) {
                continue;
            }

            try {
                $relation = $model->{$method->getName()}();
            } catch (\Throwable) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $definition = $this->definitionFor($method->getName(), $relation);
            if ($definition !== null) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * UDE query builder for a model table/connection.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function universal(string $modelClass, QueryExecutor $executor): QueryBuilder
    {
        $this->assertModel($modelClass);

        /** @var Model $model */
        $model = (new ReflectionClass($modelClass))->newInstanceWithoutConstructor();

        return QueryBuilder::table($executor, $model->getTable());
    }

    private function definitionFor(string $name, Relation $relation): ?RelationDefinition
    {
        if ($relation instanceof BelongsTo) {
            return RelationDefinition::belongsTo(
                $name,
                TableIdentifier::fromString($relation->getRelated()->getTable()),
                $relation->getForeignKeyName(),
                $relation->getOwnerKeyName() ?: $relation->getRelated()->getKeyName(),
            )->withMetadata(['model' => $relation->getRelated()::class]);
        }

        if ($relation instanceof HasOne) {
            return RelationDefinition::hasOne(
                $name,
                TableIdentifier::fromString($relation->getRelated()->getTable()),
                $relation->getForeignKeyName(),
                $relation->getLocalKeyName() ?: $relation->getParent()->getKeyName(),
            )->withMetadata(['model' => $relation->getRelated()::class]);
        }

        if ($relation instanceof HasMany) {
            return RelationDefinition::hasMany(
                $name,
                TableIdentifier::fromString($relation->getRelated()->getTable()),
                $relation->getForeignKeyName(),
                $relation->getLocalKeyName() ?: $relation->getParent()->getKeyName(),
            )->withMetadata(['model' => $relation->getRelated()::class]);
        }

        if ($relation instanceof BelongsToMany) {
            return RelationDefinition::belongsToMany(
                $name,
                TableIdentifier::fromString($relation->getRelated()->getTable()),
                TableIdentifier::fromString($relation->getTable()),
                $relation->getForeignPivotKeyName(),
                $relation->getRelatedPivotKeyName(),
            )->withMetadata(['model' => $relation->getRelated()::class]);
        }

        if ($relation instanceof MorphOne) {
            return RelationDefinition::morphOne(
                $name,
                TableIdentifier::fromString($relation->getRelated()->getTable()),
                $relation->getForeignKeyName(),
                $relation->getMorphType(),
                $relation->getLocalKeyName(),
            )->withMetadata(['model' => $relation->getRelated()::class]);
        }

        if ($relation instanceof MorphMany) {
            return RelationDefinition::morphMany(
                $name,
                TableIdentifier::fromString($relation->getRelated()->getTable()),
                $relation->getForeignKeyName(),
                $relation->getMorphType(),
                $relation->getLocalKeyName(),
            )->withMetadata(['model' => $relation->getRelated()::class]);
        }

        // MorphTo is dynamic (the related model depends on the row) and other
        // relation families are intentionally not guessed.
        return null;
    }

    private function assertModel(string $modelClass): void
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException('['.$modelClass.'] is not an Eloquent model.');
        }
    }
}
