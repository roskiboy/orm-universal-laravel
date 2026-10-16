<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;

/**
 * Builds schema operations expressed as a TableDefinition, compiled to DDL by the
 * dialect migration compiler. Destructive operations must be explicit.
 *
 * @api public
 */
interface SchemaBuilderInterface
{
    /**
     * @return list<CompiledQuery>
     */
    public function create(TableDefinition $table, bool $ifNotExists = false): array;

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $indexes
     * @param  list<string>  $foreignKeys
     * @return list<CompiledQuery>
     */
    public function drop(string $table, array $columns = [], array $indexes = [], array $foreignKeys = []): array;

    /**
     * @return list<CompiledQuery>
     */
    public function dropIfExists(string $table): array;

    /**
     * @return list<CompiledQuery>
     */
    public function rename(string $from, string $to): array;

    /**
     * @return list<CompiledQuery>
     */
    public function truncate(string $table): array;
}
