<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised by the schema and migration engines, including safety rejections.
 *
 * @api public
 */
class MigrationException extends PersistenceException {}
