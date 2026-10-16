<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Full-text predicate. Dialects compile it to MATCH ... AGAINST (MySQL/MariaDB)
 * or to_tsvector ... @@ tsquery (PostgreSQL); unsupported engines throw with
 * alternatives.
 *
 * @api public
 */
final class FullText extends Expression
{
    /**
     * @param  list<string>  $columns
     * @param  array{mode?: string|null, expanded?: bool, language?: string|null, vector?: bool}  $options
     */
    public function __construct(
        public readonly array $columns,
        public readonly Expression $value,
        public readonly array $options = [],
    ) {}

    public function nodeType(): string
    {
        return 'FullText';
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->value];
    }
}
