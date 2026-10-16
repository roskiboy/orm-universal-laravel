<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\ComparisonOperator;

/**
 * JSON access expression. Dialects translate it to their own JSON operators
 * (->>, JSON_VALUE, JSON_UNQUOTE(JSON_EXTRACT(...)), or the SQLite json_extract).
 *
 * @api public
 */
final class JsonExpression extends Expression
{
    public function __construct(
        public readonly Expression $expression,
        public readonly string $path,
        public readonly bool $asText = false,
    ) {}

    public function text(): self
    {
        return new self($this->expression, $this->path, true);
    }

    public function contains(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::JsonContains, Expression::normalizeOperand($value));
    }

    public function containsKey(string $key): Comparison
    {
        return new Comparison($this, ComparisonOperator::JsonContainsKey, new Value($key));
    }

    public function overlaps(mixed $value): Comparison
    {
        return new Comparison($this, ComparisonOperator::JsonOverlaps, Expression::normalizeOperand($value));
    }

    public function length(): JsonLengthExpression
    {
        return new JsonLengthExpression($this);
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->expression];
    }
}
