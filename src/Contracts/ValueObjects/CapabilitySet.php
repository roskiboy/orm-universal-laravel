<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;

/**
 * Immutable set of capabilities for a backend/version. Keys are free-form strings
 * so third parties can register new capabilities without touching the core.
 *
 * @api public
 */
final class CapabilitySet
{
    /**
     * @param  array<string, bool|string|int|float|array>  $capabilities
     */
    public function __construct(
        private readonly array $capabilities = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * @param  list<string>  $names
     */
    public static function of(array $names): self
    {
        return new self(array_fill_keys($names, true));
    }

    public function supports(string $capability): bool
    {
        return (bool) ($this->capabilities[$capability] ?? false);
    }

    public function value(string $capability, mixed $default = null): mixed
    {
        return $this->capabilities[$capability] ?? $default;
    }

    public function with(string $capability, bool|string|int|float|array $value = true): self
    {
        return new self(array_merge($this->capabilities, [$capability => $value]));
    }

    public function without(string $capability): self
    {
        $copy = $this->capabilities;
        unset($copy[$capability]);

        return new self($copy);
    }

    public function merge(self $other): self
    {
        return new self(array_merge($this->capabilities, $other->capabilities));
    }

    /**
     * @return array<string, bool|string|int|float|array>
     */
    public function all(): array
    {
        return $this->capabilities;
    }

    /**
     * @param  list<string>  $required
     * @return list<string>
     */
    public function missing(array $required): array
    {
        return array_values(array_filter($required, fn (string $capability): bool => ! $this->supports($capability)));
    }

    public function diff(self $other): self
    {
        $names = array_keys(array_diff_key($this->capabilities, $other->capabilities));

        return self::of($names);
    }

    /**
     * @param  list<string>  $required
     */
    public function requireAll(array $required, string $engine, string $engineVersion, string $reason = 'Capability not supported'): void
    {
        $missing = $this->missing($required);

        if ($missing !== []) {
            throw UnsupportedFeatureException::forFeature(implode(', ', $missing), $engine, $engineVersion, $reason);
        }
    }
}
