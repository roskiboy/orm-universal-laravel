<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\CompiledQuery;

/**
 * Abstraction over PDO / native connections.
 *
 * @api public
 */
interface ConnectionInterface
{
    /**
     * Execute a compiled select query and return a cursor over raw rows.
     *
     * @return CursorInterface<array<string, mixed>>
     */
    public function select(CompiledQuery $query): CursorInterface;

    /**
     * Execute a statement (insert/update/delete/DDL).
     *
     * @return int affected rows
     */
    public function execute(CompiledQuery $query): int;

    /**
     * Fetch a single row (or null).
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(CompiledQuery $query): ?array;

    /**
     * Fetch a single scalar value.
     */
    public function selectValue(CompiledQuery $query): mixed;

    public function prepare(string $sql): StatementInterface;

    public function transaction(): TransactionInterface;

    public function lastInsertId(?string $sequence = null): string|int;

    public function driverName(): string;

    public function serverVersion(): string;

    public function ping(): bool;

    public function close(): void;

    public function rawPdo(): ?\PDO;
}
