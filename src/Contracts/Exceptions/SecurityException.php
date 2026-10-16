<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when a security guard rejects an operation (injection attempts,
 * mass assignment protection, cache poisoning, sensitive logging, etc.).
 *
 * @api public
 */
class SecurityException extends PersistenceException {}
