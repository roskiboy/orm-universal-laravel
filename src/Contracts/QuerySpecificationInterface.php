<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Composable specification that can be applied to a query object. Implementations
 * must not execute the query.
 *
 * @api public
 */
interface QuerySpecificationInterface
{
    public function apply(object $query): void;
}
