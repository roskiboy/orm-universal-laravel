<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Type;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Applies Laravel-style cast definitions to raw database rows inside the core,
 * without depending on Laravel. Decimals stay strings (never floats), JSON-like
 * casts decode to arrays/objects and enums resolve through their backed class.
 *
 * Laravel-compatible JSON date casts are available without importing Illuminate:
 * `eloquent_date`, `eloquent_datetime` and `eloquent_json` serialize exactly like
 * Eloquent's `serializeDate()` (`Y-m-d\T00:00:00.000000Z` / `Y-m-d\TH:i:s.u\Z`).
 *
 * @api public
 */
final class CastApplier
{
    /** @param array<string, mixed> $casts attribute => cast definition */
    public function __construct(
        private readonly array $casts = [],
    ) {}

    /**
     * @param  array<string, mixed>  $casts
     */
    public static function for(array $casts): self
    {
        return new self($casts);
    }

    /**
     * @return array<string, mixed>
     */
    public function casts(): array
    {
        return $this->casts;
    }

    public function apply(string $column, mixed $value): mixed
    {
        if ($value === null || ! array_key_exists($column, $this->casts)) {
            return $value;
        }

        return $this->cast($value, $this->casts[$column]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function applyRow(array $row): array
    {
        foreach ($this->casts as $column => $cast) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $this->cast($row[$column], $cast);
            }
        }

        return $row;
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function applyMany(iterable $rows): array
    {
        $casted = [];

        foreach ($rows as $row) {
            $casted[] = $this->applyRow($row);
        }

        return $casted;
    }

    private function cast(mixed $value, mixed $cast): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($cast) && class_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
            /** @var class-string<BackedEnum> $cast */
            return $cast::from($value);
        }

        if (! is_string($cast)) {
            return $value;
        }

        return match (strtolower($cast)) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json', 'collection' => $this->decodeJson($value),
            'object' => $this->decodeJsonObject($value),
            'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => $this->toDateTime($value),
            'eloquent_date' => $this->toEloquentDate($value),
            'eloquent_datetime', 'eloquent_immutable_datetime', 'eloquent_timestamp' => $this->toEloquentDateTime($value),
            'eloquent_json' => $this->toEloquentJson($value),
            'uuid' => strtolower((string) $value),
            'vector' => $this->toVector($value),
            default => $this->decimal($value, $cast),
        };
    }

    private function decimal(mixed $value, string $cast): mixed
    {
        if (! str_starts_with(strtolower($cast), 'decimal') && ! str_starts_with(strtolower($cast), 'numeric')) {
            return $value;
        }

        $precision = null;
        if (preg_match('/:(\d+)/', $cast, $matches) === 1) {
            $precision = (int) $matches[1];
        }

        if (is_string($value) || is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $precision === null ? (string) $value : number_format($value, $precision, '.', '');
        }

        return (string) $value;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeJsonObject(mixed $value): mixed
    {
        if (is_object($value)) {
            return $value;
        }

        return json_decode((string) $value);
    }

    private function toDateTime(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return $value;
        }
    }

    /**
     * Eloquent-compatible date-only serialization: `Y-m-d\T00:00:00.000000Z`.
     */
    private function toEloquentDate(mixed $value): mixed
    {
        $date = $this->toLocalDateTime($value);

        return $date?->format('Y-m-d\T00:00:00.000000\Z') ?? $value;
    }

    /**
     * Eloquent-compatible datetime serialization (UTC, microseconds):
     * `Y-m-d\TH:i:s.u\Z`.
     */
    private function toEloquentDateTime(mixed $value): mixed
    {
        $date = $this->toUtcDateTime($value);

        return $date?->format('Y-m-d\TH:i:s.u\Z') ?? $value;
    }

    /**
     * Eloquent-compatible JSON date/datetime: keeps the original shape (date
     * only vs datetime) and serializes in UTC like Eloquent's `toJSON()`.
     */
    private function toEloquentJson(mixed $value): mixed
    {
        $isDateOnly = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1;

        return $isDateOnly
            ? $this->toEloquentDate($value)
            : $this->toEloquentDateTime($value);
    }

    private function toLocalDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new DateTimeImmutable((string) $value, new DateTimeZone(date_default_timezone_get()));
        } catch (\Exception) {
            return null;
        }
    }

    private function toUtcDateTime(mixed $value): ?DateTimeImmutable
    {
        return $this->toLocalDateTime($value)?->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @return list<float>
     */
    private function toVector(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): float => (float) $item, $value);
        }

        $string = trim((string) $value, "[] \t\n\r\0\x0B");

        if ($string === '') {
            return [];
        }

        return array_map(
            static fn (string $item): float => (float) trim($item),
            explode(',', $string),
        );
    }
}
