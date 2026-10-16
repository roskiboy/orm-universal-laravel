<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\ConnectionConfig;

/**
 * Creates connections for a given driver/config pair.
 *
 * @api public
 */
interface DriverInterface
{
    public function name(): string;

    public function connect(ConnectionConfig $config): ConnectionInterface;

    public function supports(ConnectionConfig $config): bool;

    /**
     * @return list<string>
     */
    public function requiredExtensions(): array;
}
