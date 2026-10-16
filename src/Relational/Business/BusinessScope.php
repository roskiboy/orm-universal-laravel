<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use OrmUniversal\Contracts\Exceptions\BusinessOperationException;

/**
 * Named business scope: a first class query intention (paid, overdue, highValue)
 * that is resolvable, cacheable and traceable.
 *
 * @api public
 */
final class BusinessScope
{
    /**
     * @param  callable(object): void|class-string  $definition
     */
    public function __construct(
        private readonly string $name,
        private $definition,
        private readonly ?string $model = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function definition(): callable|string
    {
        return $this->definition;
    }

    public function apply(object $query): object
    {
        $definition = $this->definition;

        if (is_callable($definition)) {
            $definition($query);

            return $query;
        }

        if (! class_exists($definition)) {
            throw new BusinessOperationException('Business scope class ['.$definition.'] does not exist.');
        }

        $instance = new $definition;

        if (! method_exists($instance, 'apply')) {
            throw new BusinessOperationException('Business scope class ['.$definition.'] must expose apply().');
        }

        $instance->apply($query);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'definition' => is_string($this->definition) ? $this->definition : 'closure',
        ];
    }
}
