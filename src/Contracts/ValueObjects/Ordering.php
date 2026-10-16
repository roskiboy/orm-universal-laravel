<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use OrmUniversal\Contracts\Enums\SortDirection;

/**
 * A single ORDER BY item. Null placement is optional and dialect negotiated.
 *
 * @api public
 */
final class Ordering
{
    public function __construct(
        public readonly string|object $expression,
        public readonly SortDirection $direction = SortDirection::Asc,
        public readonly ?bool $nullsFirst = null,
    ) {}

    public static function asc(string|object $expression): self
    {
        return new self($expression, SortDirection::Asc);
    }

    public static function desc(string|object $expression): self
    {
        return new self($expression, SortDirection::Desc);
    }
}
