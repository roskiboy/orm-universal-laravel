<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Compiler;

use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Mutable per-compilation state: ordered bindings, dialect and safety flags.
 *
 * @api public
 */
final class CompileContext
{
    /** @var list<mixed> */
    public array $bindings = [];

    /** @var list<string> */
    public array $warnings = [];

    public function __construct(
        public readonly AbstractDialect $dialect,
        public readonly bool $strictIdentifiers = true,
        public readonly int $depth = 0,
    ) {}

    /**
     * Record a non-fatal translation warning (ignored hint, emulated feature,
     * degraded statement). Warnings never change the returned data.
     */
    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function bind(mixed $value): string
    {
        $this->bindings[] = $value;

        return '?';
    }

    /**
     * @param  list<mixed>  $values
     */
    public function bindMany(array $values): string
    {
        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->bind($value);
        }

        return implode(', ', $placeholders);
    }

    public function placeholder(string $name): string
    {
        $this->bindings[] = new ParameterPlaceholder($name);

        return '?';
    }

    public function nested(): self
    {
        $context = new self($this->dialect, $this->strictIdentifiers, $this->depth + 1);
        $context->bindings = &$this->bindings;
        $context->warnings = &$this->warnings;

        return $context;
    }

    public function bindingCount(): int
    {
        return count($this->bindings);
    }
}
