<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Compatibility;

use OrmUniversal\Contracts\Enums\CompatibilityMode;
use OrmUniversal\Contracts\Exceptions\QueryException;

/**
 * Guards the adoption modes: Legacy, Compatibility, Hybrid, Optimized, Native
 * and Strict. Strict mode rejects the documented legacy shortcuts.
 *
 * @api public
 */
final class LaravelCompatibilityLayer
{
    private CompatibilityMode $mode;

    public function __construct(?CompatibilityMode $mode = null)
    {
        $this->mode = $mode ?? CompatibilityMode::Hybrid;
    }

    public function mode(): CompatibilityMode
    {
        return $this->mode;
    }

    public function use(CompatibilityMode $mode): void
    {
        $this->mode = $mode;
    }

    public function allowsLegacyApis(): bool
    {
        return $this->mode->allowsLegacyApis();
    }

    /**
     * Strict mode rejects raw SQL without explicit classification.
     */
    public function guardRawSql(string $sql, bool $explicit = false): void
    {
        if ($this->mode !== CompatibilityMode::Strict) {
            return;
        }

        if (! $explicit) {
            throw new QueryException(
                'Strict mode: raw SQL must be wrapped in SqlFragment::trusted()/parameterized() to declare its nature. SQL: '.substr($sql, 0, 120),
            );
        }
    }

    public function guardLegacyApi(string $api): void
    {
        if ($this->mode === CompatibilityMode::Strict) {
            throw new QueryException('Strict mode: legacy API ['.$api.'] is not allowed.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'mode' => $this->mode->value,
            'legacy_apis_allowed' => $this->allowsLegacyApis(),
            'guarantees' => [
                'eloquent_results_unchanged',
                'ordering_unchanged',
                'null_semantics_unchanged',
                'transaction_semantics_unchanged',
                'casting_unchanged',
                'events_unchanged',
            ],
        ];
    }
}
