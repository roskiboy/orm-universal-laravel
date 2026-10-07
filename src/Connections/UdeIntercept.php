<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use OrmUniversal\Laravel\Adapters\LaravelConnectionAdapter;
use OrmUniversal\Relational\Execution\QueryExecutor;
use WeakMap;

/**
 * Gate and shared services for the transparent AST fast path used by the
 * `ude-*` drivers.
 *
 * Activation is opt-in: `orm-universal.auto.mode=intercept` (env `UDE_AUTO=intercept`)
 * or `orm-universal.auto.intercept=true`. `UDE_AUTO_INTERCEPT=false` is an
 * absolute kill switch. Connections whose driver does not start with `ude-`
 * are never intercepted.
 *
 * @api public
 */
final class UdeIntercept
{
    private static ?LaravelConnectionAdapter $adapter = null;

    /** @var WeakMap<Connection, QueryExecutor>|null */
    private static ?WeakMap $executors = null;

    private static ?UdeQueryMapper $mapper = null;

    public static function enabled(?Connection $connection = null): bool
    {
        if ($connection === null) {
            return false;
        }

        if (! str_starts_with((string) $connection->getDriverName(), 'ude-')) {
            return false;
        }

        $flag = self::flag();

        if ($flag === false) {
            return false;
        }

        if ($flag === true) {
            return true;
        }

        return (string) self::config('orm-universal.auto.mode', 'monitor') === 'intercept';
    }

    public static function mapper(): UdeQueryMapper
    {
        return self::$mapper ??= new UdeQueryMapper;
    }

    public static function executor(Connection $connection): QueryExecutor
    {
        self::$executors ??= new WeakMap;
        self::$adapter ??= new LaravelConnectionAdapter;

        if (! isset(self::$executors[$connection])) {
            self::$executors[$connection] = self::$adapter->executor($connection);
        }

        return self::$executors[$connection];
    }

    /**
     * Drop cached executors. Invoked from the Octane/queue flush hook so long
     * running workers never keep state across requests.
     */
    public static function flush(): void
    {
        self::$executors = null;
        self::$adapter = null;
    }

    private static function flag(): ?bool
    {
        $value = self::config('orm-universal.auto.intercept');

        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        return null;
    }

    private static function config(string $key, mixed $default = null): mixed
    {
        $container = Container::getInstance();

        if (! $container->bound('config')) {
            return $default;
        }

        $config = $container->make('config');

        return method_exists($config, 'get') ? $config->get($key, $default) : $default;
    }
}
