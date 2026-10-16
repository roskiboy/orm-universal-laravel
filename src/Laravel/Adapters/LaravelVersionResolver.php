<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use Illuminate\Foundation\Application;

/**
 * Resolves Laravel version information. The adapter never branches on versions by
 * itself: it exposes data and defers capability decisions to the feature registry.
 *
 * @api public
 */
final class LaravelVersionResolver
{
    public function __construct(
        private readonly ?Application $app = null,
        private readonly ?LaravelFeatureRegistry $features = null,
    ) {}

    public function version(): string
    {
        return $this->app?->version() ?? Application::VERSION;
    }

    public function major(): int
    {
        return (int) explode('.', $this->version())[0];
    }

    public function isAtLeast(int $major): bool
    {
        return $this->major() >= $major;
    }

    public function features(): LaravelFeatureRegistry
    {
        return $this->features ?? new LaravelFeatureRegistry;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return [
            'version' => $this->version(),
            'major' => $this->major(),
            'php' => PHP_VERSION,
            'features' => $this->features()->all(),
        ];
    }
}
