<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * Vector distance expression: MariaDB compiles vec_distance_cosine(col,
 * vec_fromtext(?)); PostgreSQL compiles (col <=> ?) with pgvector. Unsupported
 * engines throw UnsupportedFeatureException with alternatives.
 *
 * @api public
 */
final class VectorDistance extends Expression
{
    public function __construct(
        public readonly Expression $column,
        public readonly Value $vector,
    ) {}

    public static function of(string|Expression $column, mixed $vector): self
    {
        return new self(
            $column instanceof Expression ? $column : Column::make($column),
            new Value(Expression::normalizeVector($vector)),
        );
    }

    public function nodeType(): string
    {
        return 'VectorDistance';
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->column, $this->vector];
    }
}
