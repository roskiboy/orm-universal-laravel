<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

/**
 * Named or positional parameter placeholder resolved at execution time.
 *
 * @api public
 */
final class Parameter extends Expression
{
    public function __construct(
        public readonly ?string $name = null,
    ) {}
}
