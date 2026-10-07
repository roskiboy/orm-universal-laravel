<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\MariaDbConnection;

/**
 * MariaDB connection whose query builder uses the UDE AST fast path.
 *
 * @api public
 */
final class UdeMariaDbConnection extends MariaDbConnection
{
    use CreatesUdeQueryBuilder;
}
