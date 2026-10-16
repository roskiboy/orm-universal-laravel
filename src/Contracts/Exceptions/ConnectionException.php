<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised for connect, ping, reconnect and connection configuration failures.
 *
 * @api public
 */
class ConnectionException extends PersistenceException {}
