<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Optimizer;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Exceptions\CompilationException;
use OrmUniversal\Relational\Ast\DeleteQuery;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\LogicalExpression;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Ast\UpdateQuery;

/**
 * Structural AST validation. It never executes anything and never mutates the
 * query; it fails fast with a descriptive CompilationException.
 *
 * @api public
 */
final class Validator
{
    public function validate(AstNodeInterface $node): void
    {
        if ($node instanceof SelectQuery) {
            if ($node->table() === null && $node->fromSubquery() === null) {
                throw new CompilationException('SELECT requires a FROM source (table or derived table).');
            }
        }

        if ($node instanceof InsertQuery) {
            if ($node->rowsData() === [] && $node->selectQuery() === null) {
                throw new CompilationException('INSERT requires values() or fromSelect().');
            }
        }

        if ($node instanceof UpdateQuery && $node->values() === []) {
            throw new CompilationException('UPDATE requires at least one SET value.');
        }

        if ($node instanceof DeleteQuery) {
            // Destructive policy is enforced at execution time, not here.
        }

        if ($node instanceof LogicalExpression && $node->parts() === []) {
            throw new CompilationException('Empty logical expression group is not allowed.');
        }
    }

    /**
     * @return list<string> structural warnings (non fatal)
     */
    public function warnings(AstNodeInterface $node): array
    {
        $warnings = [];

        if ($node instanceof UpdateQuery && $node->whereExpression() === null) {
            $warnings[] = 'UPDATE without WHERE clause.';
        }

        if ($node instanceof DeleteQuery && $node->whereExpression() === null) {
            $warnings[] = 'DELETE without WHERE clause.';
        }

        return $warnings;
    }
}
