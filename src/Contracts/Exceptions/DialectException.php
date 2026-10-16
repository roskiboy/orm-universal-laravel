<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when a dialect is missing, misconfigured or asked to compile an unknown node.
 *
 * @api public
 */
class DialectException extends PersistenceException {}
