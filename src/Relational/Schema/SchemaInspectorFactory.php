<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Schema;

use OrmUniversal\Contracts\Exceptions\DialectException;
use OrmUniversal\Contracts\SchemaInspectorInterface;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Execution\QueryExecutor;

/**
 * Builds the right schema inspector for a dialect.
 *
 * @api public
 */
final class SchemaInspectorFactory
{
    public static function for(AbstractDialect $dialect, QueryExecutor $executor): SchemaInspectorInterface
    {
        return match ($dialect->vendor()->value) {
            'mysql', 'mariadb' => new MySqlSchemaInspector($executor),
            'postgresql' => new PostgreSqlSchemaInspector($executor),
            'sqlite' => new SqliteSchemaInspector($executor),
            'sqlserver' => new SqlServerSchemaInspector($executor),
            'oracle' => new OracleSchemaInspector($executor),
            default => throw new DialectException('No schema inspector registered for ['.$dialect->name().'].'),
        };
    }
}
