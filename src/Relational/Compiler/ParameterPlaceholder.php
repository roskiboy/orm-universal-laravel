<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Compiler;

/**
 * Placeholder for a named parameter that is resolved at execution time by the
 * connection (bindings are never interpolated into SQL).
 *
 * @api public
 */
final class ParameterPlaceholder
{
    public function __construct(
        public readonly string $name,
    ) {}
}
