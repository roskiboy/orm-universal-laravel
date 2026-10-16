<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Output of compiling a single expression.
 *
 * @api public
 */
final class CompiledExpression
{
    /**
     * @param  list<mixed>  $bindings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings = [],
    ) {}

    public function __toString(): string
    {
        return $this->sql;
    }
}
