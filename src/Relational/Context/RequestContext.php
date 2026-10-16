<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

/**
 * Request metadata context (HTTP or worker job). Transported explicitly; never
 * stored in globals so Octane/Swoole/RoadRunner stay leak-free.
 *
 * @api public
 */
final class RequestContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly string $requestId,
        private readonly ?int $userId = null,
        private readonly ?string $ip = null,
        private readonly ?string $locale = null,
        private readonly array $metadata = [],
    ) {}

    public static function make(
        string $requestId,
        ?int $userId = null,
        ?string $ip = null,
        ?string $locale = null,
        array $metadata = [],
    ): self {
        return new self($requestId, $userId, $ip, $locale, $metadata);
    }

    public static function generate(?string $prefix = 'req'): self
    {
        return new self($prefix.'_'.bin2hex(random_bytes(8)));
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'ip' => $this->ip,
            'locale' => $this->locale,
            'metadata' => $this->metadata,
        ];
    }
}
