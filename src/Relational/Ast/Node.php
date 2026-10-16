<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Base AST node. nodeType() is derived from the class name without reflection so
 * it is safe to call in hot paths.
 *
 * @api public
 */
abstract class Node implements AstNodeInterface
{
    public function nodeType(): string
    {
        $class = static::class;
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [];
    }
}
