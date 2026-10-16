<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

/**
 * Literal value. Always compiled as a binding, never interpolated.
 *
 * @api public
 */
final class Value extends Expression
{
    public function __construct(
        public readonly mixed $value,
    ) {}
}
