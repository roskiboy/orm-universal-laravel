<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use OrmUniversal\Contracts\Enums\IndexType;

/**
 * Index definition (BTree, Hash, FullText, Spatial, GIN, GiST, BRIN, BItmap, vector...).
 *
 * @api public
 */
final class IndexDefinition
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly bool $unique = false,
        public readonly bool $primary = false,
        public readonly IndexType $type = IndexType::BTree,
        public readonly ?string $where = null,
        public readonly ?string $algorithm = null,
        public readonly ?string $using = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'unique' => $this->unique,
            'primary' => $this->primary,
            'type' => $this->type->value,
            'where' => $this->where,
            'algorithm' => $this->algorithm,
            'using' => $this->using,
        ];
    }
}
