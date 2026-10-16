<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Expressions are first class AST nodes. Values are never interpolated into SQL;
 * they travel as bindings.
 *
 * @api public
 */
interface ExpressionInterface extends AstNodeInterface {}
