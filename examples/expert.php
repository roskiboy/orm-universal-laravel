<?php

// FRAGMENTO de integración: requiere una app Laravel con orm-universal/laravel
// instalado. NO ejecutar standalone.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Laravel\Ude;
use OrmUniversal\Relational\Ast\Column;

// --- Kernel y adapters ------------------------------------------------------

$kernel = app(Ude::class);              // singleton registrado por el provider
$kernel = OrmUniversal::kernel();       // equivalente vía facade

$executor = $kernel->connections()->executor('mysql');       // QueryExecutor sobre PDO vivo
$dialect = $kernel->connections()->dialectFor('mysql');      // dialecto detectado
$summary = $kernel->connections()->summary(DB::connection()); // driver/database/version

$kernel->dialects()->vendors();          // vendors registrados
$kernel->types()->has('jsonb');          // tipos universales
$kernel->features()->report();           // features de Laravel detectadas
$kernel->plugins()->names();             // plugins registrados

// --- Business scopes (explícitos y trazables) -------------------------------

$kernel->business()->registerScope('activos', function ($query): void {
    $query->where(Column::make('is_active')->eq(true));
}, model: 'sellers');

$kernel->business()->registerOperation('archive', function (int $id): int {
    return OrmUniversal::table('sellers')->where('id', $id)->update(['is_active' => false]);
}, model: 'sellers');

// --- NoSQL desde la misma config --------------------------------------------

$kernel->bootNoSql((array) config('orm-universal.nosql', []));

$events = $kernel->nosql()->collection('events');
$events->insertMany([
    ['_id' => 'e1', 'type' => 'login', 'user' => 'ana'],
]);

$kernel->nosql()->defaultDriver();      // 'mongodb' | 'redis' | 'in-memory'

// --- Octane / workers largos ------------------------------------------------

// El provider ya escucha RequestTerminated/TaskTerminated/TickTerminated/
// JobProcessed/JobFailed y ejecuta flush(). En un worker propio:
$kernel->flush();

// --- Tooling -----------------------------------------------------------------

// php artisan ude:doctor
// php artisan ude:dialect:inspect --connection=mysql
// php artisan ude:query:inspect sellers --where=is_active=1 --explain
