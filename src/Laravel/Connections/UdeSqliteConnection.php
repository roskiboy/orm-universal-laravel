<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\SQLiteConnection;

/**
 * SQLite connection whose query builder uses the UDE AST fast path.
 *
 * @api public
 */
final class UdeSqliteConnection extends SQLiteConnection
{
    use CreatesUdeQueryBuilder;
}
