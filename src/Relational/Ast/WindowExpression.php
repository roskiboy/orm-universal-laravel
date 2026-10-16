<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\ValueObjects\Ordering;

/**
 * Window function: function OVER (PARTITION BY ... ORDER BY ... frame).
 *
 * @api public
 */
final class WindowExpression extends Expression
{
    /**
     * @param  list<Expression>  $partitionBy
     * @param  list<Ordering>  $orderBy
     */
    public function __construct(
        public readonly Expression $function,
        public readonly ?self $window = null,
        public readonly array $partitionBy = [],
        public readonly array $orderBy = [],
        public readonly ?string $frame = null,
        public readonly ?string $windowName = null,
    ) {}

    public static function make(Expression $function): self
    {
        return new self($function);
    }

    public function partitionBy(Expression ...$expressions): self
    {
        return new self($this->function, $this->window, array_merge($this->partitionBy, $expressions), $this->orderBy, $this->frame, $this->windowName);
    }

    public function orderBy(Ordering ...$orderings): self
    {
        return new self($this->function, $this->window, $this->partitionBy, array_merge($this->orderBy, $orderings), $this->frame, $this->windowName);
    }

    public function frame(string $frame): self
    {
        return new self($this->function, $this->window, $this->partitionBy, $this->orderBy, $frame, $this->windowName);
    }

    /**
     * Named window reference: OVER "name". The WINDOW clause itself (or a CTE)
     * is the caller's responsibility; the compiler never invents definitions.
     */
    public function named(string $windowName): self
    {
        return new self($this->function, null, $this->partitionBy, $this->orderBy, $this->frame, $windowName);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        $children = [$this->function, ...$this->partitionBy];

        if ($this->window !== null) {
            $children[] = $this->window;
        }

        foreach ($this->orderBy as $ordering) {
            if ($ordering->expression instanceof Expression) {
                $children[] = $ordering->expression;
            }
        }

        return $children;
    }
}
