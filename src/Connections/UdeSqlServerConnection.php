<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\SqlServerConnection;

/**
 * SQL Server connection whose query builder uses the UDE AST fast path.
 * Implemented without real integration (no server available locally); the
 * dialect and builder remain compiler-tested and are not declared stable.
 *
 * @api public
 */
final class UdeSqlServerConnection extends SqlServerConnection
{
    use CreatesUdeQueryBuilder;
}
