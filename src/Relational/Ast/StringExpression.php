<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

/**
 * String function expression (lower, upper, length, trim, substring, concat...).
 * Kept as a distinct node so portability reports can classify string semantics.
 *
 * @api public
 */
final class StringExpression extends FunctionCall {}
