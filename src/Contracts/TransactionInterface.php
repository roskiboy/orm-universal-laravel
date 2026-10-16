<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\Enums\TransactionState;

/**
 * Transaction handle with savepoint support when the backend provides it.
 *
 * @api public
 */
interface TransactionInterface
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    /**
     * Whether savepoints are supported by the current backend/version.
     */
    public function supportsSavepoints(): bool;

    public function savepoint(string $name): void;

    public function rollbackTo(string $name): void;

    public function releaseSavepoint(string $name): void;

    public function state(): TransactionState;

    public function level(): int;

    public function isActive(): bool;
}
