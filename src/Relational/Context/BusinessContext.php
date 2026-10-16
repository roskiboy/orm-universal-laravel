<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

use OrmUniversal\Relational\Business\BusinessRegistry;

/**
 * Business context: registry, tenant and the caller identity used to resolve
 * business scopes/operations traceably.
 *
 * @api public
 */
final class BusinessContext
{
    private function __construct(
        private readonly BusinessRegistry $registry,
        private readonly ?string $tenant = null,
        private readonly ?int $userId = null,
        private ?string $lastResolution = null,
    ) {}

    public static function make(BusinessRegistry $registry, ?string $tenant = null, ?int $userId = null): self
    {
        return new self($registry, $tenant, $userId);
    }

    public function registry(): BusinessRegistry
    {
        return $this->registry;
    }

    public function tenant(): ?string
    {
        return $this->tenant;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    /**
     * Traceability: which scope/operation/specification was resolved last.
     */
    public function markResolved(string $description): void
    {
        $this->lastResolution = $description;
    }

    public function lastResolution(): ?string
    {
        return $this->lastResolution;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant' => $this->tenant,
            'user_id' => $this->userId,
            'scopes' => count($this->registry->scopes()),
            'last_resolution' => $this->lastResolution,
        ];
    }
}
