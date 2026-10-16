<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as LaravelPaginator;
use OrmUniversal\Contracts\PaginatorInterface;

/**
 * Converts UDE paginators into native Laravel paginators so responses, links and
 * JSON stay drop-in compatible.
 *
 * @api public
 */
final class LaravelPaginatorAdapter
{
    public static function lengthAware(
        PaginatorInterface $source,
        string $path = '/',
        array $query = [],
    ): LengthAwarePaginator {
        return new LengthAwarePaginator(
            items: self::items($source),
            total: (int) ($source->total() ?? count(self::items($source))),
            perPage: $source->perPage(),
            currentPage: $source->currentPage(),
            options: ['path' => $path, 'query' => $query],
        );
    }

    public static function simple(
        PaginatorInterface $source,
        string $path = '/',
        array $query = [],
    ): LaravelPaginator {
        return new LaravelPaginator(
            items: self::items($source),
            perPage: $source->perPage(),
            currentPage: $source->currentPage(),
            options: ['path' => $path, 'query' => $query],
        );
    }

    /**
     * @return list<mixed>
     */
    public static function items(PaginatorInterface $source): array
    {
        return is_array($source->items()) ? $source->items() : iterator_to_array($source->items(), false);
    }
}
