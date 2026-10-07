<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Adoption mode
    |--------------------------------------------------------------------------
    |
    | legacy | compatibility | hybrid | optimized | native | strict
    |
    | Hybrid keeps vanilla Eloquent fully working while UDE queries, recordsets
    | and business scopes are used side by side.
    |
    */
    'compatibility_mode' => env('UDE_COMPATIBILITY_MODE', 'hybrid'),

    /*
    |--------------------------------------------------------------------------
    | Performance mode
    |--------------------------------------------------------------------------
    |
    | safe | balanced | fast | extreme. Extreme reduces optional instrumentation
    | but never disables security, bindings or transactional correctness.
    |
    */
    'performance_mode' => env('UDE_PERFORMANCE_MODE', 'balanced'),

    /*
    |--------------------------------------------------------------------------
    | N+1 detection
    |--------------------------------------------------------------------------
    |
    | ignore | warning | log | exception | telemetry
    |
    */
    'n_plus_one' => [
        'mode' => env('UDE_N_PLUS_ONE_MODE', 'warning'),
        'threshold' => (int) env('UDE_N_PLUS_ONE_THRESHOLD', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profiler
    |--------------------------------------------------------------------------
    */
    'profiler' => [
        'enabled' => (bool) env('UDE_PROFILER_ENABLED', true),
        'limit' => (int) env('UDE_PROFILER_LIMIT', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | PSR-14 events
    |--------------------------------------------------------------------------
    |
    | When enabled, the platform emits QueryExecuted, TransactionLifecycle,
    | N1Detected and RetryScheduled. Optional; no provider is mandatory.
    |
    */
    'events' => [
        'enabled' => (bool) env('UDE_EVENTS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic mode
    |--------------------------------------------------------------------------
    |
    | monitor: zero-risk observability (N+1, duplicates, slow queries).
    | intercept: monitor + transparent AST fast path on ude-* connections.
    | off: disables the automatic layer entirely.
    |
    | intercept accepts strict booleans: true forces the fast path on,
    | false (env UDE_AUTO_INTERCEPT=false) is an absolute kill switch.
    | When unset, `mode=intercept` decides.
    |
    */
    'auto' => [
        'mode' => env('UDE_AUTO', 'monitor'),
        'intercept' => env('UDE_AUTO_INTERCEPT'),
        'slow_ms' => (int) env('UDE_AUTO_SLOW_MS', 200),
        'n_plus_one_threshold' => (int) env('UDE_AUTO_N1_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query result cache (opt-in) and plan cache
    |--------------------------------------------------------------------------
    |
    | `enabled` caches results (opt-in, explicit invalidation). `plan` caches
    | compiled SQL and defaults to on when intercept mode is active.
    | `reuse` reuses duplicate reads inside a request (risk: stale reads).
    |
    */
    'cache' => [
        'enabled' => (bool) env('UDE_CACHE_ENABLED', false),
        'store' => env('UDE_CACHE_STORE'),
        'ttl' => (int) env('UDE_CACHE_TTL', 60),
        'plan' => env('UDE_PLAN_CACHE'),
        'plan_ttl' => (int) env('UDE_PLAN_CACHE_TTL', 3600),
        'reuse' => env('UDE_QUERY_REUSE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Destructive statement policy
    |--------------------------------------------------------------------------
    |
    | allow | warn | require_confirmation | deny
    |
    */
    'destructive_statements' => env('UDE_DESTRUCTIVE_STATEMENTS', 'warn'),

    /*
    |--------------------------------------------------------------------------
    | Hot paths (opt-in per-read UDE rewrites)
    |--------------------------------------------------------------------------
    */
    'hotpaths' => [
        'treasury_summary' => env('UDE_HOTPATH_TREASURY_SUMMARY', false),
        'banking_range_totals' => env('UDE_HOTPATH_BANKING_TOTALS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tooling
    |--------------------------------------------------------------------------
    */
    'tooling' => [
        'report_path' => storage_path('ude'),
        'model_paths' => [app_path('Models')],
        'model_namespace' => 'App\\Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | NoSQL drivers
    |--------------------------------------------------------------------------
    */
    'nosql' => [
        'default' => env('UDE_NOSQL_DEFAULT', 'mongodb'),
        'drivers' => [
            'mongodb' => [
                'enabled' => (bool) env('UDE_MONGODB_ENABLED', true),
                'uri' => env('UDE_MONGODB_URI', 'mongodb://127.0.0.1:27017'),
                'database' => env('UDE_MONGODB_DATABASE', 'ude'),
            ],
            'redis' => [
                'enabled' => (bool) env('UDE_REDIS_ENABLED', false),
                'host' => env('UDE_REDIS_HOST', '127.0.0.1'),
                'port' => (int) env('UDE_REDIS_PORT', 6379),
                'prefix' => env('UDE_REDIS_PREFIX', 'ude'),
            ],
            'in_memory' => [
                'enabled' => (bool) env('UDE_IN_MEMORY_ENABLED', true),
            ],
        ],
    ],

];
