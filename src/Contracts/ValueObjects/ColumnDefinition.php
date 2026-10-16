<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use OrmUniversal\Contracts\Enums\ConstraintType;
use OrmUniversal\Contracts\Enums\TypeKind;

/**
 * Dialect independent column definition used by schema inspection, migration AST
 * and code generation.
 *
 * @api public
 */
final class ColumnDefinition
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $name,
        public string $databaseType,
        public TypeKind $kind = TypeKind::Custom,
        public bool $nullable = true,
        public mixed $default = null,
        public bool $autoIncrement = false,
        public bool $unsigned = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public ?string $comment = null,
        public ?string $generatedExpression = null,
        public bool $generatedStored = false,
        public ?ConstraintType $constraint = null,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'database_type' => $this->databaseType,
            'kind' => $this->kind->value,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'auto_increment' => $this->autoIncrement,
            'unsigned' => $this->unsigned,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'comment' => $this->comment,
            'generated_expression' => $this->generatedExpression,
            'generated_stored' => $this->generatedStored,
            'constraint' => $this->constraint?->value,
            'extra' => $this->extra,
        ];
    }
}
