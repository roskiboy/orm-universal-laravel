<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use OrmUniversal\Contracts\Exceptions\BusinessOperationException;

/**
 * Named business operation: a domain action with explicit arguments.
 *
 * @api public
 */
final class BusinessOperation
{
    /**
     * @param  callable(mixed...): mixed|class-string  $handler
     */
    public function __construct(
        private readonly string $name,
        private $handler,
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

    public function handle(mixed ...$arguments): mixed
    {
        $handler = $this->handler;

        if (is_callable($handler)) {
            return $handler(...$arguments);
        }

        if (! class_exists($handler)) {
            throw new BusinessOperationException('Business operation class ['.$handler.'] does not exist.');
        }

        $instance = new $handler;

        if (method_exists($instance, 'handle')) {
            return $instance->handle(...$arguments);
        }

        if (method_exists($instance, '__invoke')) {
            return $instance(...$arguments);
        }

        throw new BusinessOperationException('Business operation class ['.$handler.'] must expose handle() or __invoke().');
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return [
            'name' => $this->name,
            'model' => $this->model,
            'handler' => is_string($this->handler) ? $this->handler : 'closure',
        ];
    }
}
