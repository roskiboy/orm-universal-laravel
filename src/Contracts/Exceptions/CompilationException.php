<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when an AST or expression cannot be translated into SQL safely.
 *
 * @api public
 */
class CompilationException extends PersistenceException {}
