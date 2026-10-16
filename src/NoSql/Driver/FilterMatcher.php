<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Driver;

/**
 * Backend neutral filter evaluator used by the in-memory driver and by tests.
 *
 * @api public
 */
final class FilterMatcher
{
    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $filter
     */
    public function matches(array $document, array $filter): bool
    {
        foreach ($filter as $field => $condition) {
            if ($field === '$or') {
                $matched = false;
                foreach ((array) $condition as $subFilter) {
                    if ($this->matches($document, (array) $subFilter)) {
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    return false;
                }

                continue;
            }

            if ($field === '$and') {
                foreach ((array) $condition as $subFilter) {
                    if (! $this->matches($document, (array) $subFilter)) {
                        return false;
                    }
                }

                continue;
            }

            if (! $this->matchesField($document, $field, $condition)) {
                return false;
            }
        }

        return true;
    }

    private function matchesField(array $document, string $field, mixed $condition): bool
    {
        $value = $this->extract($document, $field);
        $exists = $this->exists($document, $field);

        if (! is_array($condition) || $this->isScalarList($condition)) {
            return $value == $condition;
        }

        foreach ($condition as $operator => $operand) {
            $result = match ($operator) {
                '$eq' => $value == $operand,
                '$ne' => $value != $operand,
                '$gt' => $value !== null && $value > $operand,
                '$gte' => $value !== null && $value >= $operand,
                '$lt' => $value !== null && $value < $operand,
                '$lte' => $value !== null && $value <= $operand,
                '$in' => in_array($value, (array) $operand, false),
                '$nin' => ! in_array($value, (array) $operand, false),
                '$exists' => $exists === (bool) $operand,
                '$regex' => is_string($value) && preg_match('/'.str_replace('/', '\\/', (string) $operand).'/', $value) === 1,
                '$size' => is_array($value) && count($value) === (int) $operand,
                default => throw new \InvalidArgumentException("Unsupported NoSQL operator [{$operator}]."),
            };

            if (! $result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dot notation extraction (a.b.c).
     *
     * @param  array<string, mixed>  $document
     */
    public function extract(array $document, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $document;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function exists(array $document, string $path): bool
    {
        $segments = explode('.', $path);
        $value = $document;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    private function isScalarList(array $value): bool
    {
        return $value === [] || array_is_list($value);
    }
}
