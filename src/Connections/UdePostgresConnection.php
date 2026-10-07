<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\PostgresConnection;

/**
 * PostgreSQL connection whose query builder uses the UDE AST fast path.
 *
 * @api public
 */
final class UdePostgresConnection extends PostgresConnection
{
    use CreatesUdeQueryBuilder;
}
