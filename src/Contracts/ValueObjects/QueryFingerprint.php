<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Stable identity of a query shape: same SQL normalization + same binding count +
 * same structural hash means the queries are equivalent for caching/profiling.
 *
 * @api public
 */
final class QueryFingerprint implements \Stringable
{
    public function __construct(
        public readonly string $normalizedSql,
        public readonly string $hash,
        public readonly int $bindingCount,
        public readonly ?string $structuralHash = null,
    ) {}

    public static function fromSql(string $sql, int $bindingCount, ?string $structuralHash = null): self
    {
        $normalized = self::normalize($sql);

        return new self($normalized, hash('sha256', $normalized), $bindingCount, $structuralHash);
    }

    /**
     * Replace literals, quoted strings and whitespace runs so textually different
     * but equivalent statements share a fingerprint.
     */
    public static function normalize(string $sql): string
    {
        $normalized = preg_replace("/'(?:[^']|'')*'/", '?', $sql) ?? $sql;
        $normalized = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return strtolower(trim($normalized));
    }

    public function equals(self $other): bool
    {
        return $this->hash === $other->hash && $this->bindingCount === $other->bindingCount;
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}
