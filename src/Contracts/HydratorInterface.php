<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\Enums\HydrationMode;

/**
 * Converts raw rows into the configured hydration mode.
 *
 * @api public
 */
interface HydratorInterface
{
    public function mode(): HydrationMode;

    /**
     * @param  array<string, mixed>  $row
     */
    public function hydrate(array $row): mixed;

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return iterable<int, mixed>
     */
    public function hydrateMany(iterable $rows): iterable;
}
