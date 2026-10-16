<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised for immutable context violations, tenant leaks or long-running worker
 * context problems (Octane, Swoole, RoadRunner, queues).
 *
 * @api public
 */
class ContextException extends PersistenceException {}
