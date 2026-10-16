<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;

/**
 * JSON length expression: compiled to json_length / jsonb_array_length /
 * json_array_length / openjson count depending on the dialect.
 *
 * @api public
 */
final class JsonLengthExpression extends Expression
{
    public function __construct(
        public readonly JsonExpression $json,
    ) {}

    public function nodeType(): string
    {
        return 'JsonLength';
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return [$this->json];
    }
}
