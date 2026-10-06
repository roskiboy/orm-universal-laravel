<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Analysis;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionMethod;

/**
 * Model intelligence: table, connection, casts, scopes, accessors, mutators,
 * relations and events for a given model class.
 *
 * @api public
 */
final class ModelInspector
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>
     */
    public function inspect(string $modelClass): array
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return ['class' => $modelClass, 'error' => 'Not an Eloquent model'];
        }

        $reflection = new ReflectionClass($modelClass);

        /** @var Model $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        return [
            'class' => $modelClass,
            'table' => $instance->getTable(),
            'connection' => $instance->getConnectionName(),
            'primary_key' => $instance->getKeyName(),
            'incrementing' => $instance->getIncrementing(),
            'timestamps' => $instance->usesTimestamps(),
            'soft_deletes' => method_exists($instance, 'trashed'),
            'casts' => $instance->getCasts(),
            'fillable' => $instance->getFillable(),
            'guarded' => $instance->getGuarded(),
            'hidden' => $instance->getHidden(),
            'appends' => array_values($instance->getAppends()),
            'relations' => $this->relations($reflection),
            'scopes' => $this->scopes($reflection),
            'accessors_mutators' => $this->accessorsAndMutators($reflection),
            'observers' => $this->observers($modelClass),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function relations(ReflectionClass $reflection): array
    {
        $relations = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            $returnType = $method->getReturnType();
            $type = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : '';

            if (str_starts_with($type, 'Illuminate\\Database\\Eloquent\\Relations\\')) {
                $relations[$method->getName()] = substr($type, strrpos($type, '\\') + 1);
            }
        }

        return $relations;
    }

    /**
     * @return list<string>
     */
    private function scopes(ReflectionClass $reflection): array
    {
        $scopes = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'scope')) {
                $scopes[] = lcfirst(substr($method->getName(), 5));
            }
        }

        return $scopes;
    }

    /**
     * @return list<string>
     */
    private function accessorsAndMutators(ReflectionClass $reflection): array
    {
        $found = [];

        foreach ($reflection->getMethods() as $method) {
            $name = $method->getName();

            if (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute')) {
                $found[] = 'accessor:'.lcfirst(substr($name, 3, -9));
            }

            if (str_starts_with($name, 'set') && str_ends_with($name, 'Attribute')) {
                $found[] = 'mutator:'.lcfirst(substr($name, 3, -9));
            }

            if (str_ends_with($name, 'UsingAttribute') || in_array($name, ['casts', 'newAccessors'], true)) {
                $found[] = 'modern:'.$name;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string>
     */
    private function observers(string $modelClass): array
    {
        $observers = [];

        if (method_exists($modelClass, 'getEventDispatcher')) {
            $dispatcher = $modelClass::getEventDispatcher();
            if ($dispatcher !== null && method_exists($dispatcher, 'getListeners')) {
                foreach (['creating', 'created', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored'] as $event) {
                    $listeners = $dispatcher->getListeners('eloquent.'.$event.': '.$modelClass);
                    if ($listeners !== []) {
                        $observers[] = $event;
                    }
                }
            }
        }

        return $observers;
    }
}
