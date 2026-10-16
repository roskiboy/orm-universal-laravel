<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\CompiledQuery;

/**
 * Compiles schema operations into DDL statements for a dialect version.
 *
 * @api public
 */
interface MigrationCompilerInterface
{
    /**
     * @return list<CompiledQuery>
     */
    public function compile(SchemaOperationInterface $operation): array;

    public function supports(SchemaOperationInterface $operation): bool;
}
