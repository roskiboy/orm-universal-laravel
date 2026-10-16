<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Observability;

/**
 * Optional PSR-14 bridge. When no dispatcher is configured events are simply
 * dropped; the platform never requires an external event provider.
 *
 * @api public
 */
final class EventEmitter
{
    public function __construct(
        private readonly ?object $dispatcher = null,
    ) {}

    public function hasDispatcher(): bool
    {
        return $this->dispatcher !== null && method_exists($this->dispatcher, 'dispatch');
    }

    public function emit(object $event): object
    {
        if (is_object($this->dispatcher) && method_exists($this->dispatcher, 'dispatch')) {
            return $this->dispatcher->dispatch($event) ?? $event;
        }

        return $event;
    }

    public function dispatcher(): ?object
    {
        return $this->dispatcher;
    }
}
