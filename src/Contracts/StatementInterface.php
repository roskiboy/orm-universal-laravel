<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Prepared statement.
 *
 * @api public
 */
interface StatementInterface
{
    /**
     * @param  array<int|string, mixed>  $bindings
     */
    public function execute(array $bindings = []): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAll(): array;

    public function fetchColumn(int $column = 0): mixed;

    public function rowCount(): int;

    public function close(): void;
}
