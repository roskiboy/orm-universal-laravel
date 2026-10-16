<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

/**
 * Extension point: dialects, types, optimizers, business methods and Laravel
 * integrations can be contributed through plugins.
 *
 * @api public
 */
interface PluginInterface
{
    public function name(): string;

    /**
     * Register everything the plugin provides into the given kernel/registrar.
     */
    public function register(object $kernel): void;

    public function boot(object $kernel): void;
}
