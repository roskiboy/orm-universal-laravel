<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\ColumnDefinition;
use OrmUniversal\Contracts\ValueObjects\ForeignKeyDefinition;
use OrmUniversal\Contracts\ValueObjects\IndexDefinition;
use OrmUniversal\Contracts\ValueObjects\SchemaSnapshot;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

/**
 * Reads schema metadata from a live database.
 *
 * @api public
 */
interface SchemaInspectorInterface
{
    /**
     * @return list<string>
     */
    public function tables(?string $schema = null): array;

    public function table(TableIdentifier $table): ?TableDefinition;

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(TableIdentifier $table): array;

    /**
     * @return list<IndexDefinition>
     */
    public function indexes(TableIdentifier $table): array;

    /**
     * @return list<ForeignKeyDefinition>
     */
    public function foreignKeys(TableIdentifier $table): array;

    public function snapshot(?string $schema = null): SchemaSnapshot;
}
