<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

/**
 * Per-instance resolution cache: method signatures, scopes, business methods,
 * relations and attributes are resolved once, never per call.
 *
 * @api public
 */
final class MethodCache
{
    /** @var array<string, mixed> */
    private array $entries = [];

    private int $hits = 0;

    private int $misses = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    public function remember(string $key, callable $resolver): mixed
    {
        if (array_key_exists($key, $this->entries)) {
            $this->hits++;

            return $this->entries[$key];
        }

        $this->misses++;

        return $this->entries[$key] = $resolver();
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * @return array{entries: int, hits: int, misses: int}
     */
    public function stats(): array
    {
        return [
            'entries' => count($this->entries),
            'hits' => $this->hits,
            'misses' => $this->misses,
        ];
    }
}
