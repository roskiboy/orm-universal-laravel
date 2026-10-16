<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Plugin;

use OrmUniversal\Contracts\PluginInterface;

/**
 * Plugin registry: dialects, types, optimizers, business methods and adapters can
 * be contributed without touching the core.
 *
 * @api public
 */
final class PluginRegistry
{
    /** @var array<string, PluginInterface> */
    private array $plugins = [];

    /** @var list<string> */
    private array $booted = [];

    public function register(PluginInterface $plugin, bool $override = false): void
    {
        if (! $override && isset($this->plugins[$plugin->name()])) {
            throw new \InvalidArgumentException('Plugin ['.$plugin->name().'] is already registered.');
        }

        $this->plugins[$plugin->name()] = $plugin;
    }

    public function has(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    public function get(string $name): PluginInterface
    {
        return $this->plugins[$name] ?? throw new \InvalidArgumentException('Unknown plugin ['.$name.'].');
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->plugins);
    }

    public function registerAll(object $kernel): void
    {
        foreach ($this->plugins as $plugin) {
            $plugin->register($kernel);
        }
    }

    public function bootAll(object $kernel): void
    {
        foreach ($this->plugins as $name => $plugin) {
            if (in_array($name, $this->booted, true)) {
                continue;
            }

            $plugin->boot($kernel);
            $this->booted[] = $name;
        }
    }

    /**
     * @return list<string>
     */
    public function booted(): array
    {
        return $this->booted;
    }
}
