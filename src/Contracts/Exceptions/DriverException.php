<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when a PDO/native driver operation fails at the transport level.
 *
 * @api public
 */
class DriverException extends PersistenceException {}
