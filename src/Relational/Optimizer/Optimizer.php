<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\OptimizerPassInterface;
use OrmUniversal\Relational\Ast\Expression;
use OrmUniversal\Relational\Ast\SelectQuery;

/**
 * Semantic-safe optimizer. Passes never change query results.
 *
 * @api public
 */
final class Optimizer
{
    /** @var list<OptimizerPassInterface> */
    private array $passes;

    /**
     * @param  list<OptimizerPassInterface>|null  $passes
     */
    public function __construct(?array $passes = null)
    {
        $this->passes = $passes ?? [
            new Pass\DuplicatePredicatePass,
            new Pass\BooleanSimplificationPass,
            new Pass\ConstantFoldingPass,
            new Pass\InToEqualsPass,
            new Pass\ProjectionPruningPass,
        ];
    }

    public function addPass(OptimizerPassInterface $pass): void
    {
        $this->passes[] = $pass;
    }

    /**
     * @return list<string>
     */
    public function passNames(): array
    {
        return array_map(static fn (OptimizerPassInterface $pass): string => $pass->name(), $this->passes);
    }

    public function optimize(AstNodeInterface $node): AstNodeInterface
    {
        if ($node instanceof SelectQuery) {
            if (($where = $node->whereExpression()) !== null) {
                $node->replaceWhere($this->optimizeExpression($where));
            }

            if (($having = $node->havingExpression()) !== null) {
                $node->replaceHaving($this->optimizeExpression($having));
            }
        }

        return $node;
    }

    public function optimizeExpression(Expression $expression): Expression
    {
        return array_reduce(
            $this->passes,
            static function (Expression $carry, OptimizerPassInterface $pass): Expression {
                $result = $pass->apply($carry);

                return $result instanceof Expression ? $result : $carry;
            },
            $expression,
        );
    }
}
