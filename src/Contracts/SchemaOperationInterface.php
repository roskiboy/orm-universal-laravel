<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Marker for schema/migration operations compiled to DDL.
 *
 * @api public
 */
interface SchemaOperationInterface
{
    public function operationName(): string;
}
