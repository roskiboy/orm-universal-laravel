<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Hydration;

use OrmUniversal\Contracts\EntityInterface;
use OrmUniversal\Contracts\Enums\HydrationMode;
use OrmUniversal\Contracts\Exceptions\HydrationException;
use OrmUniversal\Contracts\HydratorInterface;

/**
 * Single hydrator implementation covering the four documented modes:
 * zero (array), light (stdClass), entity (class + EntityInterface or public
 * properties) and eloquent (framework factory callable supplied by the adapter).
 *
 * @api public
 */
final class Hydrator implements HydratorInterface
{
    /**
     * @param  class-string|null  $entityClass
     * @param  (callable(array<string, mixed>): object)|null  $factory
     */
    public function __construct(
        private readonly HydrationMode $hydrationMode = HydrationMode::Zero,
        private readonly ?string $entityClass = null,
        private $factory = null,
    ) {}

    public static function zero(): self
    {
        return new self(HydrationMode::Zero);
    }

    public static function light(): self
    {
        return new self(HydrationMode::Light);
    }

    /**
     * @param  class-string  $class
     */
    public static function entity(string $class): self
    {
        return new self(HydrationMode::Entity, $class);
    }

    /**
     * @param  callable(array<string, mixed>): object  $factory
     */
    public static function factory(callable $factory): self
    {
        return new self(HydrationMode::Eloquent, null, $factory);
    }

    public function mode(): HydrationMode
    {
        return $this->hydrationMode;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function hydrate(array $row): mixed
    {
        return match ($this->hydrationMode) {
            HydrationMode::Zero => $row,
            HydrationMode::Light => (object) $row,
            HydrationMode::Entity => $this->hydrateEntity($row),
            HydrationMode::Eloquent => ($this->factory)($row),
        };
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return iterable<int, mixed>
     */
    public function hydrateMany(iterable $rows): iterable
    {
        foreach ($rows as $key => $row) {
            yield $key => $this->hydrate($row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hydrateEntity(array $row): object
    {
        if ($this->entityClass === null) {
            throw new HydrationException('Entity hydration requires a class name.');
        }

        if (! class_exists($this->entityClass)) {
            throw new HydrationException('Entity class ['.$this->entityClass.'] does not exist.');
        }

        $reflection = new \ReflectionClass($this->entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();

        if ($entity instanceof EntityInterface) {
            foreach ($row as $attribute => $value) {
                $entity->setAttribute((string) $attribute, $value);
            }

            return $entity;
        }

        foreach ($row as $attribute => $value) {
            $property = (string) $attribute;

            if ($reflection->hasProperty($property)) {
                $reflectionProperty = $reflection->getProperty($property);
                $reflectionProperty->setValue($entity, $value);
            } else {
                $entity->{$property} = $value;
            }
        }

        return $entity;
    }
}
