<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Pagination result, compatible with Laravel paginators through adapters.
 *
 * @api public
 */
interface PaginatorInterface extends \JsonSerializable
{
    /**
     * @return iterable<int, mixed>
     */
    public function items(): iterable;

    public function total(): ?int;

    public function perPage(): int;

    public function currentPage(): int;

    public function lastPage(): ?int;

    public function hasMorePages(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
