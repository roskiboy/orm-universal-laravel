<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OrmUniversal\Laravel\Ude;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Execution\QueryExecutor;

/**
 * OrmUniversal facade.
 *
 * @method static Ude kernel()
 * @method static QueryBuilder table(string $table, string $connection = 'default')
 * @method static QueryExecutor executor(string $connection = 'default')
 * @method static \OrmUniversal\Relational\Dialect\DialectRegistry dialects()
 * @method static \OrmUniversal\Relational\Type\TypeRegistry types()
 * @method static \OrmUniversal\Relational\Business\BusinessRegistry business()
 * @method static \OrmUniversal\NoSql\DocumentStore nosql()
 * @method static \OrmUniversal\Relational\Observability\InMemoryProfiler profiler()
 * @method static \OrmUniversal\Laravel\Adapters\LaravelFeatureRegistry features()
 * @method static \OrmUniversal\Laravel\Compatibility\LaravelCompatibilityLayer compatibility()
 * @method static \OrmUniversal\Laravel\Adapters\LaravelConnectionAdapter connections()
 *
 * @api public
 */
final class OrmUniversal extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Ude::class;
    }

    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        /** @var Ude $kernel */
        $kernel = self::getFacadeRoot();

        return QueryBuilder::table(
            $kernel->connections()->executor(
                $connection ?? (string) config('database.default'),
                $kernel->profiler(),
            ),
            $table,
        );
    }

    public static function executor(?string $connection = null): QueryExecutor
    {
        /** @var Ude $kernel */
        $kernel = self::getFacadeRoot();

        return $kernel->connections()->executor(
            $connection ?? (string) config('database.default'),
            $kernel->profiler(),
        );
    }

    public static function kernel(): Ude
    {
        /** @var Ude $kernel */
        $kernel = self::getFacadeRoot();

        return $kernel;
    }
}
