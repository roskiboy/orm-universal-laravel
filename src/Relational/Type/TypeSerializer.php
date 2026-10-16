<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use OrmUniversal\Contracts\TypeInterface;

/**
 * Serializes PHP values into their database representation before binding.
 *
 * @api public
 */
final class TypeSerializer
{
    public function serialize(mixed $value, TypeInterface $type): mixed
    {
        return $type->serialize($value);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, TypeInterface>  $types
     * @return array<string, mixed>
     */
    public function serializeRow(array $values, array $types): array
    {
        foreach ($types as $attribute => $type) {
            if (array_key_exists($attribute, $values)) {
                $values[$attribute] = $type->serialize($values[$attribute]);
            }
        }

        return $values;
    }
}
