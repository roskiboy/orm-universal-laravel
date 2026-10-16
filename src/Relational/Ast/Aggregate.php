<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\AggregateFunction;

/**
 * Aggregate invocation (COUNT, SUM, AVG, GROUP_CONCAT, STRING_AGG, ...).
 * Dialects map the logical aggregate to the vendor specific function.
 *
 * @api public
 */
final class Aggregate extends Expression
{
    public function __construct(
        public readonly AggregateFunction $function,
        public readonly Expression|string $expression = '*',
        public readonly bool $distinct = false,
        public readonly ?string $separator = null,
    ) {}

    public static function countAll(): self
    {
        return new self(AggregateFunction::Count, '*');
    }

    public function asCount(): self
    {
        return new self(AggregateFunction::Count, $this->expression, $this->distinct);
    }

    public function over(?WindowExpression $window = null): WindowExpression
    {
        return new WindowExpression($this, $window);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return $this->expression instanceof Expression ? [$this->expression] : [];
    }
}
