<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * BETWEEN / NOT BETWEEN predicate.
 *
 * @api public
 */
final class Between extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        public readonly Expression $low,
        public readonly Expression $high,
        public readonly bool $negated = false,
    ) {}

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->expression, $this->low, $this->high];
    }
}
