<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

use OrmUniversal\Contracts\Enums\HydrationMode;

/**
 * Hydration context: mode, target class, factory and cast map used while
 * converting raw rows into PHP values.
 *
 * @api public
 */
final class HydrationContext
{
    /**
     * @param  class-string|null  $entityClass
     * @param  (callable(array<string, mixed>): object)|null  $factory
     * @param  array<string, string>  $casts  attribute => type name
     */
    private function __construct(
        private readonly HydrationMode $mode = HydrationMode::Zero,
        private readonly ?string $entityClass = null,
        private $factory = null,
        private readonly array $casts = [],
    ) {}

    public static function make(
        HydrationMode $mode = HydrationMode::Zero,
        ?string $entityClass = null,
        ?callable $factory = null,
        array $casts = [],
    ): self {
        return new self($mode, $entityClass, $factory, $casts);
    }

    public function mode(): HydrationMode
    {
        return $this->mode;
    }

    public function entityClass(): ?string
    {
        return $this->entityClass;
    }

    /**
     * @return (callable(array<string, mixed>): object)|null
     */
    public function factory(): ?callable
    {
        return $this->factory;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return $this->casts;
    }

    public function hasCasts(): bool
    {
        return $this->casts !== [];
    }
}
