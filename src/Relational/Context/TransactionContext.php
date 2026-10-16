<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Context;

use OrmUniversal\Contracts\Enums\TransactionState;
use OrmUniversal\Contracts\TransactionInterface;

/**
 * Transaction context: propagates the active transaction to relations,
 * repositories and queries without global coupling.
 *
 * @api public
 */
final class TransactionContext
{
    private function __construct(
        private readonly TransactionInterface $transaction,
        private readonly string $name = 'default',
    ) {}

    public static function for(TransactionInterface $transaction, string $name = 'default'): self
    {
        return new self($transaction, $name);
    }

    public function transaction(): TransactionInterface
    {
        return $this->transaction;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->transaction->isActive();
    }

    public function level(): int
    {
        return $this->transaction->level();
    }

    public function state(): TransactionState
    {
        return $this->transaction->state();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state()->value,
            'level' => $this->level(),
            'savepoints' => $this->transaction->supportsSavepoints(),
        ];
    }
}
