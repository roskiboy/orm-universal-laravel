<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator as LaravelSimplePaginator;
use OrmUniversal\Laravel\Adapters\LaravelPaginatorAdapter;
use OrmUniversal\Laravel\OrmUniversalServiceProvider;
use OrmUniversal\Relational\Builder\QueryBuilder;

/**
 * Bridges UDE paginators into native Laravel paginators while keeping the
 * relational core free of Illuminate dependencies.
 *
 * The macros are registered by {@see OrmUniversalServiceProvider}
 * so callers can use `->paginateLaravel()` / `->simplePaginateLaravel()` directly
 * on any UDE query builder, including `zeroHydration()` and join queries.
 *
 * @api public
 */
final class LaravelPagination
{
    public static function register(): void
    {
        (new self)->registerMacros();
    }

    private function registerMacros(): void
    {
        if (! QueryBuilder::hasMacro('paginateLaravel')) {
            QueryBuilder::macro('paginateLaravel', function (
                int $perPage = 15,
                ?int $page = null,
                ?string $path = null,
                ?array $query = null,
            ): LengthAwarePaginator {
                return LaravelPagination::paginateFromObject($this, $perPage, $page, $path, $query);
            });
        }

        if (! QueryBuilder::hasMacro('simplePaginateLaravel')) {
            QueryBuilder::macro('simplePaginateLaravel', function (
                int $perPage = 15,
                ?int $page = null,
                ?string $path = null,
                ?array $query = null,
            ): LaravelSimplePaginator {
                return LaravelPagination::simplePaginateFromObject($this, $perPage, $page, $path, $query);
            });
        }
    }

    /**
     * Macro entry point: Macroable binds `$this` to the UDE builder at runtime.
     *
     * @internal
     *
     * @param  array<string, mixed>|null  $query
     */
    public static function paginateFromObject(
        object $builder,
        int $perPage = 15,
        ?int $page = null,
        ?string $path = null,
        ?array $query = null,
    ): LengthAwarePaginator {
        if (! $builder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('paginateLaravel() must be called on an OrmUniversal query builder.');
        }

        return self::paginate($builder, $perPage, $page, $path, $query);
    }

    /**
     * Macro entry point: Macroable binds `$this` to the UDE builder at runtime.
     *
     * @internal
     *
     * @param  array<string, mixed>|null  $query
     */
    public static function simplePaginateFromObject(
        object $builder,
        int $perPage = 15,
        ?int $page = null,
        ?string $path = null,
        ?array $query = null,
    ): LaravelSimplePaginator {
        if (! $builder instanceof QueryBuilder) {
            throw new \InvalidArgumentException('simplePaginateLaravel() must be called on an OrmUniversal query builder.');
        }

        return self::simplePaginate($builder, $perPage, $page, $path, $query);
    }

    /**
     * @param  array<string, mixed>|null  $query
     */
    public static function paginate(
        QueryBuilder $builder,
        int $perPage = 15,
        ?int $page = null,
        ?string $path = null,
        ?array $query = null,
    ): LengthAwarePaginator {
        $paginator = $builder->paginate($perPage, self::resolvePage($page));

        return LaravelPaginatorAdapter::lengthAware(
            $paginator,
            $path ?? LaravelSimplePaginator::resolveCurrentPath(),
            $query ?? self::currentQuery(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $query
     */
    public static function simplePaginate(
        QueryBuilder $builder,
        int $perPage = 15,
        ?int $page = null,
        ?string $path = null,
        ?array $query = null,
    ): LaravelSimplePaginator {
        $paginator = $builder->simplePaginate($perPage, self::resolvePage($page));

        return LaravelPaginatorAdapter::simple(
            $paginator,
            $path ?? LaravelSimplePaginator::resolveCurrentPath(),
            $query ?? self::currentQuery(),
        );
    }

    private static function resolvePage(?int $page): int
    {
        return max(1, $page ?? LaravelSimplePaginator::resolveCurrentPage());
    }

    /**
     * @return array<string, mixed>
     */
    private static function currentQuery(): array
    {
        if (! function_exists('request') || ! function_exists('app')) {
            return [];
        }

        try {
            $app = app();

            if ($app !== null && method_exists($app, 'bound') && $app->bound('request')) {
                return request()->query();
            }
        } catch (\Throwable) {
            // Console/queue context: no current request.
        }

        return [];
    }
}
