<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Contracts\Container\Container;
use Psr\Container\ContainerInterface;

/**
 * PSR-11 adapter over the Laravel container. The core never requires the
 * container; this adapter exists only inside the Laravel integration layer.
 *
 * @api public
 */
final class LaravelContainerAdapter implements ContainerInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function get(string $id): mixed
    {
        return $this->container->make($id);
    }

    public function has(string $id): bool
    {
        return $this->container->bound($id);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function make(string $id, array $parameters = []): mixed
    {
        return $this->container->make($id, $parameters);
    }

    public function bound(string $id): bool
    {
        return $this->container->bound($id);
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->container->singleton($id, $factory);
    }

    public function instance(string $id, mixed $instance): void
    {
        $this->container->instance($id, $instance);
    }

    public function container(): Container
    {
        return $this->container;
    }
}
