<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

use OrmUniversal\Contracts\Enums\HydrationMode;
use OrmUniversal\Contracts\Enums\PerformanceMode;
use OrmUniversal\Contracts\TransactionInterface;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Execution context: everything the runner needs to execute a query without
 * relying on global state.
 *
 * @api public
 */
final class ExecutionContext
{
    private function __construct(
        private readonly AbstractDialect $dialect,
        private readonly ?TransactionInterface $transaction = null,
        private readonly HydrationMode $hydrationMode = HydrationMode::Zero,
        private readonly PerformanceMode $performanceMode = PerformanceMode::Balanced,
        private readonly ?string $tenant = null,
    ) {}

    public static function make(
        AbstractDialect $dialect,
        ?TransactionInterface $transaction = null,
        HydrationMode $hydrationMode = HydrationMode::Zero,
        PerformanceMode $performanceMode = PerformanceMode::Balanced,
        ?string $tenant = null,
    ): self {
        return new self($dialect, $transaction, $hydrationMode, $performanceMode, $tenant);
    }

    public function dialect(): AbstractDialect
    {
        return $this->dialect;
    }

    public function transaction(): ?TransactionInterface
    {
        return $this->transaction;
    }

    public function hydrationMode(): HydrationMode
    {
        return $this->hydrationMode;
    }

    public function performanceMode(): PerformanceMode
    {
        return $this->performanceMode;
    }

    public function tenant(): ?string
    {
        return $this->tenant;
    }

    /**
     * Profiling is optional: extreme mode disables it, security never.
     */
    public function profilingEnabled(): bool
    {
        return $this->performanceMode->profilingEnabled();
    }

    public function withTransaction(?TransactionInterface $transaction): self
    {
        return new self($this->dialect, $transaction, $this->hydrationMode, $this->performanceMode, $this->tenant);
    }

    public function withHydrationMode(HydrationMode $mode): self
    {
        return new self($this->dialect, $this->transaction, $mode, $this->performanceMode, $this->tenant);
    }

    public function withPerformanceMode(PerformanceMode $mode): self
    {
        return new self($this->dialect, $this->transaction, $this->hydrationMode, $mode, $this->tenant);
    }

    public function withTenant(?string $tenant): self
    {
        return new self($this->dialect, $this->transaction, $this->hydrationMode, $this->performanceMode, $tenant);
    }
}
