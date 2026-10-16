<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\ValueObjects\ColumnIdentifier;

/**
 * Column reference. Never contains values; identifiers are quoted by the compiler.
 *
 * @api public
 */
final class Column extends Expression
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $table = null,
    ) {}

    public static function make(string $name): self
    {
        $identifier = ColumnIdentifier::fromString($name);

        return new self($identifier->name, $identifier->table);
    }

    /**
     * Path-aware factory: `table.column` stays a qualified column while
     * `column->json->path` becomes a JsonExpression automatically (Laravel
     * style magic paths).
     */
    public static function fromPath(string $column): Expression
    {
        if (str_contains($column, '->')) {
            [$base, $path] = explode('->', $column, 2);

            return Expression::json(self::make(trim($base)), '$.'.implode('.', array_map('trim', explode('->', $path))), true);
        }

        return self::make($column);
    }

    public static function of(string $table, string $name): self
    {
        return new self($name, $table);
    }

    public function qualifiedName(): string
    {
        return $this->table !== null ? $this->table.'.'.$this->name : $this->name;
    }
}
