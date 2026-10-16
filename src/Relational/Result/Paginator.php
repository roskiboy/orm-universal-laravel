<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Result;

use OrmUniversal\Contracts\PaginatorInterface;

/**
 * Simple length-aware paginator result.
 *
 * @api public
 */
final class Paginator implements PaginatorInterface
{
    /**
     * @param  list<mixed>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly ?int $total,
        private readonly int $perPage,
        private readonly int $page,
    ) {}

    public function items(): iterable
    {
        return $this->items;
    }

    public function total(): ?int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->page;
    }

    public function lastPage(): ?int
    {
        if ($this->total === null || $this->perPage < 1) {
            return null;
        }

        return (int) max(1, ceil($this->total / $this->perPage));
    }

    public function hasMorePages(): bool
    {
        $last = $this->lastPage();

        if ($last !== null) {
            return $this->page < $last;
        }

        return count($this->items) === $this->perPage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->page,
            'last_page' => $this->lastPage(),
            'has_more_pages' => $this->hasMorePages(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
