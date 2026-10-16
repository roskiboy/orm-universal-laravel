<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Support;

use BadMethodCallException;
use Closure;
use ReflectionMethod;

/**
 * Minimal macro registry for the relational core. ADR-001 forbids importing
 * Illuminate\Support\Macroable, so the platform ships its own deterministic
 * registry: explicit registration, no hidden magic beyond the registered names.
 *
 * @api public
 */
trait Macroable
{
    /** @var array<string, callable> */
    private static array $macros = [];

    public static function macro(string $name, callable $macro): void
    {
        static::$macros[$name] = $macro;
    }

    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    public static function mixin(object $mixin): void
    {
        foreach (get_class_methods($mixin) as $method) {
            if (str_starts_with($method, '__')) {
                continue;
            }

            $reflection = new ReflectionMethod($mixin, $method);
            if (! $reflection->isPublic()) {
                continue;
            }

            static::macro($method, [$mixin, $method]);
        }
    }

    /**
     * @param  list<mixed>  $parameters
     */
    public function macroCall(string $method, array $parameters = []): mixed
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            return $macro->bindTo($this, static::class)(...$parameters);
        }

        return $macro(...$parameters);
    }

    /**
     * @param  list<mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->macroCall($method, $parameters);
    }

    /**
     * @param  list<mixed>  $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
        }

        $macro = static::$macros[$method];

        if ($macro instanceof Closure) {
            return $macro->bindTo(null, static::class)(...$parameters);
        }

        return $macro(...$parameters);
    }
}
