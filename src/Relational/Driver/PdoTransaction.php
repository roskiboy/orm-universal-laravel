<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Driver;

use OrmUniversal\Contracts\Enums\TransactionState;
use OrmUniversal\Contracts\Exceptions\ConnectionException;
use OrmUniversal\Contracts\TransactionInterface;
use OrmUniversal\Relational\Events\TransactionLifecycle;
use PDO;
use PDOException;

/**
 * PDO transaction with savepoint support. State is tracked explicitly so long
 * running workers (Octane, queues) can detect leaked transactions.
 *
 * @api public
 */
final class PdoTransaction implements TransactionInterface
{
    private TransactionState $state = TransactionState::None;

    private int $level = 0;

    /** @var list<string> */
    private array $savepoints = [];

    public function __construct(
        private readonly PdoConnection $connection,
        private readonly bool $savepointsSupported = true,
    ) {}

    public function begin(): void
    {
        if ($this->state === TransactionState::Active) {
            throw new ConnectionException('A transaction is already active on this connection.');
        }

        try {
            $this->pdo()->beginTransaction();
        } catch (PDOException $exception) {
            $this->state = TransactionState::Failed;

            throw new ConnectionException('Unable to begin transaction: '.$exception->getMessage(), 0, $exception);
        }

        $this->state = TransactionState::Active;
        $this->level = 1;
        $this->savepoints = [];
    }

    public function commit(): void
    {
        $this->assertActive();

        try {
            $this->pdo()->commit();
        } catch (PDOException $exception) {
            $this->state = TransactionState::Failed;

            throw new ConnectionException('Unable to commit transaction: '.$exception->getMessage(), 0, $exception);
        }

        $this->state = TransactionState::Committed;
        $this->level = 0;
        $this->savepoints = [];

        $this->connection->events()->emit(new TransactionLifecycle($this->state, $this->connection->name(), 0));
    }

    public function rollback(): void
    {
        $this->assertActive();

        try {
            if ($this->savepoints !== []) {
                $this->pdo()->exec('rollback');
            } else {
                $this->pdo()->rollBack();
            }
        } catch (PDOException $exception) {
            $this->state = TransactionState::Failed;

            throw new ConnectionException('Unable to rollback transaction: '.$exception->getMessage(), 0, $exception);
        }

        $this->state = TransactionState::RolledBack;
        $this->level = 0;
        $this->savepoints = [];

        $this->connection->events()->emit(new TransactionLifecycle($this->state, $this->connection->name(), 0));
    }

    public function supportsSavepoints(): bool
    {
        return $this->savepointsSupported;
    }

    public function savepoint(string $name): void
    {
        $this->assertActive();

        if (! $this->savepointsSupported) {
            throw new ConnectionException('Savepoints are not supported by this backend.');
        }

        $this->pdo()->exec('savepoint '.$this->quoteName($name));
        $this->savepoints[] = $name;
        $this->level++;
    }

    public function rollbackTo(string $name): void
    {
        $this->assertActive();

        if (! in_array($name, $this->savepoints, true)) {
            throw new ConnectionException('Unknown savepoint ['.$name.'].');
        }

        $this->pdo()->exec('rollback to savepoint '.$this->quoteName($name));
        $this->savepoints = array_slice($this->savepoints, 0, array_search($name, $this->savepoints, true) + 1);
        $this->level = count($this->savepoints) + 1;
    }

    public function releaseSavepoint(string $name): void
    {
        $this->assertActive();

        if (! in_array($name, $this->savepoints, true)) {
            throw new ConnectionException('Unknown savepoint ['.$name.'].');
        }

        $this->pdo()->exec('release savepoint '.$this->quoteName($name));
        $this->savepoints = array_values(array_filter($this->savepoints, static fn (string $savepoint): bool => $savepoint !== $name));
        $this->level = count($this->savepoints) + 1;
    }

    public function state(): TransactionState
    {
        return $this->state;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function isActive(): bool
    {
        return $this->state === TransactionState::Active;
    }

    private function assertActive(): void
    {
        if ($this->state !== TransactionState::Active) {
            throw new ConnectionException('No active transaction (state: '.$this->state->value.').');
        }
    }

    private function quoteName(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new ConnectionException('Unsafe savepoint name ['.$name.'].');
        }

        return $name;
    }

    private function pdo(): PDO
    {
        return $this->connection->rawPdo() ?? throw new ConnectionException('The connection was closed.');
    }
}
