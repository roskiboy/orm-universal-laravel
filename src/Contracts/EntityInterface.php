<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Minimal contract for entity hydration. Framework models bridge to this through
 * adapters without the core depending on them.
 *
 * @api public
 */
interface EntityInterface
{
    /**
     * @return array<string, mixed>
     */
    public function attributes(): array;

    public function getAttribute(string $name): mixed;

    public function setAttribute(string $name, mixed $value): static;

    public function exists(): bool;
}
