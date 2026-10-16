<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use OrmUniversal\Contracts\TypeInterface;

/**
 * Casts raw database values into PHP values using the universal type system.
 *
 * @api public
 */
final class TypeCaster
{
    public function cast(mixed $value, TypeInterface $type): mixed
    {
        return $type->cast($value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, TypeInterface>  $types  attribute => type
     * @return array<string, mixed>
     */
    public function castRow(array $row, array $types): array
    {
        foreach ($types as $attribute => $type) {
            if (array_key_exists($attribute, $row)) {
                $row[$attribute] = $type->cast($row[$attribute]);
            }
        }

        return $row;
    }
}
