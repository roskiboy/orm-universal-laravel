<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\Enums\RawType;
use OrmUniversal\Contracts\ValueObjects\SqlFragment;

/**
 * Explicit raw SQL. Raw is allowed but must declare its nature and bindings.
 *
 * @api public
 */
final class RawExpression extends Expression
{
    public function __construct(
        public readonly SqlFragment $fragment,
    ) {}

    public static function trusted(string $sql): self
    {
        return new self(SqlFragment::trusted($sql));
    }

    /**
     * @param  list<mixed>  $bindings
     */
    public static function parameterized(string $sql, array $bindings): self
    {
        return new self(SqlFragment::parameterized($sql, $bindings));
    }

    public function rawType(): RawType
    {
        return $this->fragment->type;
    }
}
