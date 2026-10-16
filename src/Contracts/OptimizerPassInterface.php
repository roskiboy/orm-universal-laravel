<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * A single optimizer pass. Passes never change query semantics.
 *
 * @api public
 */
interface OptimizerPassInterface
{
    public function name(): string;

    public function apply(AstNodeInterface $node): AstNodeInterface;
}
