<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use OrmUniversal\Contracts\TypeInterface;

/**
 * Deserializes database values into PHP values after fetching.
 *
 * @api public
 */
final class TypeDeserializer
{
    public function deserialize(mixed $value, TypeInterface $type): mixed
    {
        return $type->deserialize($value);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, TypeInterface>  $types
     * @return array<string, mixed>
     */
    public function deserializeRow(array $row, array $types): array
    {
        foreach ($types as $attribute => $type) {
            if (array_key_exists($attribute, $row)) {
                $row[$attribute] = $type->deserialize($row[$attribute]);
            }
        }

        return $row;
    }
}
