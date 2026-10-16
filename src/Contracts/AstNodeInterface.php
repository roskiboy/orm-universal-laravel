<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Every node of the query AST.
 *
 * @api public
 */
interface AstNodeInterface
{
    /**
     * Stable node type used for fingerprints, portability and optimizer passes.
     */
    public function nodeType(): string;

    /**
     * Child nodes, in deterministic order.
     *
     * @return list<AstNodeInterface>
     */
    public function children(): array;
}
