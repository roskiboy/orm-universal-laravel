<?php

// Integration FRAGMENT: requires a Laravel app with OrmUniversal\Laravel
// installed. Do NOT run standalone.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Laravel\Ude;
use OrmUniversal\Relational\Ast\Column;

// --- Kernel and adapters ----------------------------------------------------

$kernel = app(Ude::class);              // singleton registered by the provider
$kernel = OrmUniversal::kernel();       // equivalent via the facade

$executor = $kernel->connections()->executor('mysql');       // QueryExecutor over the live PDO
$dialect = $kernel->connections()->dialectFor('mysql');      // detected dialect
$summary = $kernel->connections()->summary(DB::connection()); // driver/database/version

$kernel->dialects()->vendors();          // registered vendors
$kernel->types()->has('jsonb');          // universal types
$kernel->features()->report();           // detected Laravel features
$kernel->plugins()->names();             // registered plugins

// --- Business scopes (explicit and traceable) -------------------------------

$kernel->business()->registerScope('activos', function ($query): void {
    $query->where(Column::make('is_active')->eq(true));
}, model: 'sellers');

$kernel->business()->registerOperation('archive', function (int $id): int {
    return OrmUniversal::table('sellers')->where('id', $id)->update(['is_active' => false]);
}, model: 'sellers');

// --- NoSQL from the same config ---------------------------------------------

$kernel->bootNoSql((array) config('orm-universal.nosql', []));

$events = $kernel->nosql()->collection('events');
$events->insertMany([
    ['_id' => 'e1', 'type' => 'login', 'user' => 'ana'],
]);

$kernel->nosql()->defaultDriver();      // 'mongodb' | 'redis' | 'in-memory'

// --- Octane / long-lived workers --------------------------------------------

// The provider already listens to RequestTerminated/TaskTerminated/TickTerminated/
// JobProcessed/JobFailed and runs flush(). In a worker of your own:
$kernel->flush();

// --- Tooling ----------------------------------------------------------------

// php artisan ude:doctor
// php artisan ude:dialect:inspect --connection=mysql
// php artisan ude:query:inspect sellers --where=is_active=1 --explain
