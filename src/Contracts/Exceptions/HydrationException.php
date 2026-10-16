<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when raw rows cannot be converted into the requested hydration mode.
 *
 * @api public
 */
class HydrationException extends PersistenceException {}
