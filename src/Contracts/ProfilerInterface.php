<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\QueryProfileEvent;

/**
 * Observability sink. Providers (Telescope, OpenTelemetry, logs, metrics) are
 * optional; the default implementation is a no-op.
 *
 * @api public
 */
interface ProfilerInterface
{
    public function record(QueryProfileEvent $event): void;

    /**
     * @return list<QueryProfileEvent>
     */
    public function events(): array;

    public function reset(): void;
}
