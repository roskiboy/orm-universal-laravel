<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\MySqlConnection;

/**
 * MySQL connection whose query builder uses the UDE AST fast path.
 *
 * @api public
 */
final class UdeMySqlConnection extends MySqlConnection
{
    use CreatesUdeQueryBuilder;
}
