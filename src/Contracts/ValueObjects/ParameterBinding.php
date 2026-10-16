<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * A value that must travel to the database as a binding.
 *
 * @api public
 */
final class ParameterBinding
{
    public function __construct(
        public readonly mixed $value,
        public readonly ?string $type = null,
        public readonly ?string $name = null,
    ) {}
}
