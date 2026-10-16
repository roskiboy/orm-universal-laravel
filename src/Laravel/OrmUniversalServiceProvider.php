<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\OperationTerminated;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickTerminated;
use OrmUniversal\Contracts\Enums\CompatibilityMode;
use OrmUniversal\Laravel\Auto\VanillaMonitor;
use OrmUniversal\Laravel\Connections\UdeConnectionFactory;
use OrmUniversal\Laravel\Connections\UdeIntercept;
use OrmUniversal\Laravel\Console\AutoEnableCommand;
use OrmUniversal\Laravel\Console\AutoReportCommand;
use OrmUniversal\Laravel\Console\BenchmarkCommand;
use OrmUniversal\Laravel\Console\CacheClearCommand;
use OrmUniversal\Laravel\Console\CompatibilityCommand;
use OrmUniversal\Laravel\Console\DialectInspectCommand;
use OrmUniversal\Laravel\Console\DialectListCommand;
use OrmUniversal\Laravel\Console\DocsApiCommand;
use OrmUniversal\Laravel\Console\DoctorCommand;
use OrmUniversal\Laravel\Console\IndexAdviseCommand;
use OrmUniversal\Laravel\Console\IndexGenerateCommand;
use OrmUniversal\Laravel\Console\InstallCommand;
use OrmUniversal\Laravel\Console\MapCommand;
use OrmUniversal\Laravel\Console\MigrateAnalyzeCommand;
use OrmUniversal\Laravel\Console\MigrateCodemodCommand;
use OrmUniversal\Laravel\Console\ModelInspectCommand;
use OrmUniversal\Laravel\Console\ParityApiCommand;
use OrmUniversal\Laravel\Console\ParityDataCommand;
use OrmUniversal\Laravel\Console\ProjectAnalyzeCommand;
use OrmUniversal\Laravel\Console\QueryInspectCommand;
use OrmUniversal\Laravel\Console\SchemaDiffCommand;
use OrmUniversal\Laravel\Console\SchemaGenerateCommand;
use OrmUniversal\Laravel\Console\SchemaInspectCommand;
use OrmUniversal\Laravel\Console\StubsCommand;
use OrmUniversal\Laravel\Console\TypesMapCommand;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Cache\QueryCache;

/**
 * Laravel integration entry point. Registers the kernel, adapters, Octane-safe
 * context resets, the Eloquent bridge macros and the UDE artisan commands.
 *
 * @api public
 */
final class OrmUniversalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/orm-universal.php', 'orm-universal');

        $this->app->singleton(Ude::class, function ($app): Ude {
            $kernel = Ude::create();
            $kernel->compatibility()->use(CompatibilityMode::from(
                (string) ($app['config']->get('orm-universal.compatibility_mode') ?? 'hybrid'),
            ));
            $kernel->bootNoSql((array) $app['config']->get('orm-universal.nosql', []));
            $this->configureConnectionCache($app, $kernel);

            return $kernel;
        });

        $this->app->alias(Ude::class, 'orm-universal');

        $this->app->singleton(VanillaMonitor::class, fn (): VanillaMonitor => new VanillaMonitor(
            slowMs: (int) $this->app['config']->get('orm-universal.auto.slow_ms', 200),
            n1Threshold: (int) $this->app['config']->get('orm-universal.auto.n_plus_one_threshold', 3),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/orm-universal.php' => config_path('orm-universal.php'),
            ], 'orm-universal-config');

            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                MapCommand::class,
                ProjectAnalyzeCommand::class,
                ModelInspectCommand::class,
                SchemaInspectCommand::class,
                SchemaDiffCommand::class,
                SchemaGenerateCommand::class,
                MigrateAnalyzeCommand::class,
                MigrateCodemodCommand::class,
                DialectListCommand::class,
                DialectInspectCommand::class,
                QueryInspectCommand::class,
                BenchmarkCommand::class,
                CacheClearCommand::class,
                CompatibilityCommand::class,
                DocsApiCommand::class,
                ParityApiCommand::class,
                ParityDataCommand::class,
                IndexAdviseCommand::class,
                IndexGenerateCommand::class,
                StubsCommand::class,
                AutoReportCommand::class,
                AutoEnableCommand::class,
                TypesMapCommand::class,
            ]);
        }

        $this->registerEloquentBridge();
        $this->registerOctaneResets();
        $this->registerAutoMonitor();
        UdeConnectionFactory::register($this->app['db']);
    }

    private function configureConnectionCache(Application $app, Ude $kernel): void
    {
        $cache = (array) $app['config']->get('orm-universal.cache', []);

        // Plan cache is opt-in (optimize-14): measured on tms, compiling a small
        // AST costs ~18 µs while a Redis plan lookup costs ~70 µs, so caching is
        // a net loss unless the shape is heavy. When enabled, the process-level
        // CompiledPlanMemory absorbs repeated shapes before hitting the store.
        if ($this->boolOption($cache['plan'] ?? null, false) && $app->bound('cache')) {
            try {
                $store = $app['cache']->store($cache['store'] ?? null);
                $kernel->connections()->withQueryCache(new QueryCache(
                    $store,
                    'ude',
                    (int) ($cache['plan_ttl'] ?? 3600),
                ));
            } catch (\Throwable) {
                // Plan cache is an optional optimization; compile without it.
            }
        }

        if ($this->boolOption($cache['reuse'] ?? null, false)) {
            $kernel->connections()->withQueryReuse(true);
        }
    }

    private function boolOption(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return (bool) $value;
    }

    private function registerAutoMonitor(): void
    {
        if ((string) $this->app['config']->get('orm-universal.auto.mode', 'monitor') === 'off') {
            return;
        }

        if (! class_exists(QueryExecuted::class)) {
            return;
        }

        $this->app['events']->listen(QueryExecuted::class, function ($event): void {
            if ($this->app->bound(VanillaMonitor::class)) {
                $this->app->make(VanillaMonitor::class)->record($event);
            }
        });
    }

    private function registerEloquentBridge(): void
    {
        if (! class_exists(Builder::class)) {
            return;
        }

        Builder::macro('universal', function () {
            /** @var Builder $this */
            return OrmUniversal::table($this->getModel()->getTable(), $this->getModel()->getConnectionName());
        });

        Builder::macro('recordset', function () {
            /** @var Builder $this */
            return OrmUniversal::table($this->getModel()->getTable(), $this->getModel()->getConnectionName())->recordset();
        });
    }

    private function registerOctaneResets(): void
    {
        $flush = function (): void {
            if ($this->app->bound(Ude::class)) {
                $this->app->make(Ude::class)->flush();
            }

            if ($this->app->bound(VanillaMonitor::class)) {
                $this->app->make(VanillaMonitor::class)->reset();
            }

            UdeIntercept::flush();
        };

        if (class_exists(RequestTerminated::class)) {
            $this->app['events']->listen(RequestTerminated::class, $flush);
        }

        if (class_exists(OperationTerminated::class)) {
            $this->app['events']->listen(TaskTerminated::class, $flush);
            $this->app['events']->listen(TickTerminated::class, $flush);
        }

        if (class_exists(JobProcessed::class)) {
            $this->app['events']->listen(JobProcessed::class, $flush);
            $this->app['events']->listen(JobFailed::class, $flush);
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [Ude::class, 'orm-universal'];
    }
}
