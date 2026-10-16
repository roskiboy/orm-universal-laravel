<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Date part extraction (year, month, day, hour...). Dialects map it to
 * EXTRACT(), DATE_PART(), strftime() or DATEPART().
 *
 * @api public
 */
final class DateExpression extends Expression
{
    public function __construct(
        public readonly string $part,
        public readonly Expression $expression,
    ) {}

    public static function of(string $part, string|Expression $column): self
    {
        return new self($part, $column instanceof Expression ? $column : Column::make($column));
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->expression];
    }
}
