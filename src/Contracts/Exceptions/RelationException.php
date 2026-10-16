<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised for relation metadata, loading or cardinality problems.
 *
 * @api public
 */
class RelationException extends PersistenceException {}
