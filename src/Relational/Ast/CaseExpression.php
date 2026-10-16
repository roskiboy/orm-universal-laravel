<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * CASE WHEN ... THEN ... ELSE ... END expression.
 *
 * @api public
 */
final class CaseExpression extends Expression
{
    /**
     * @param  list<array{0: Expression, 1: Expression}>  $whens
     */
    public function __construct(
        public readonly array $whens = [],
        public readonly ?Expression $else = null,
        public readonly ?Expression $case = null,
    ) {}

    public static function make(?Expression $case = null): self
    {
        return new self([], null, $case);
    }

    public function when(Expression $condition, Expression $result): self
    {
        return new self([...$this->whens, [$condition, $result]], $this->else, $this->case);
    }

    public function then(Expression $result): self
    {
        return $this->when($this->case ?? new Value(true), $result);
    }

    public function else(Expression $result): self
    {
        return new self($this->whens, $result, $this->case);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        $children = [];
        if ($this->case !== null) {
            $children[] = $this->case;
        }
        foreach ($this->whens as [$condition, $result]) {
            $children[] = $condition;
            $children[] = $result;
        }
        if ($this->else !== null) {
            $children[] = $this->else;
        }

        return $children;
    }
}
