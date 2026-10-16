<?php

declare(strict_types=1);

use OrmUniversal\Contracts\EntityInterface;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;

$packageAutoload = __DIR__.'/../../vendor/autoload.php';
$monorepoAutoload = __DIR__.'/../../../../vendor/autoload.php';

require is_file($packageAutoload) ? $packageAutoload : $monorepoAutoload;

function relational_advanced_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }

    echo "[OK] {$message}\n";
}

final class RelationalAdvancedSeller implements EntityInterface
{
    public ?int $id = null;

    public ?string $name = null;

    public ?int $balance = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? $this->{$name} ?? null;
    }

    public function setAttribute(string $name, mixed $value): static
    {
        $this->attributes[$name] = $value;

        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        }

        return $this;
    }

    public function exists(): bool
    {
        return $this->id !== null;
    }
}

echo "== relational/advanced: recordsets, streaming and hydration ==\n";

$connection = new PdoConnection(new PDO('sqlite::memory:'), 'sqlite-advanced');
$executor = new QueryExecutor($connection, SqliteDialect::make('3.49.2'));

$executor->execute(new RawQuery(
    'create table sellers (id integer primary key autoincrement, name text not null, balance numeric not null default 0)',
));

$sellers = static fn (): QueryBuilder => QueryBuilder::table($executor, 'sellers');

$rows = [];
for ($i = 1; $i <= 25; $i++) {
    $rows[] = ['name' => 'seller'.$i, 'balance' => $i * 10];
}
$sellers()->insertMany($rows);

$light = $sellers()->where('balance', '>=', 200)->orderBy('balance')->light()->recordset();
relational_advanced_assert($light->count() === 6, 'light recordset counts 6 rows (balance >= 200)');
relational_advanced_assert($light->first() instanceof stdClass, 'light() hydrates stdClass');
relational_advanced_assert($light->first()->name === 'seller20', 'light() exposes properties');
relational_advanced_assert($light->last()->name === 'seller25', 'last() returns the last one');

$zero = $sellers()->where('name', 'seller1')->recordset();
relational_advanced_assert(is_array($zero->first()), 'zero hydration returns arrays');

$entity = $sellers()->where('name', 'seller3')->entity(RelationalAdvancedSeller::class)->first();
relational_advanced_assert($entity instanceof RelationalAdvancedSeller, 'entity() hydrates the class');
relational_advanced_assert($entity->name === 'seller3' && $entity->exists(), 'entity() assigns attributes and exists()');

$streamed = 0;
foreach ($sellers()->orderBy('id')->cursor() as $row) {
    relational_advanced_assert(is_array($row), 'cursor() delivers raw rows (zero hydration)');
    $streamed++;
    break;
}
foreach ($sellers()->orderBy('id')->cursor() as $ignored) {
    $streamed++;
}
relational_advanced_assert($streamed === 26, 'cursor() walks 25 rows + 1 from the break');

$chunks = 0;
$chunkRows = 0;
$sellers()->orderBy('id')->chunk(10, function (array $chunk, int $page) use (&$chunks, &$chunkRows): bool {
    $chunks++;
    $chunkRows += count($chunk);

    return true;
});
relational_advanced_assert($chunks === 3, 'chunk(10) produces 3 pages');
relational_advanced_assert($chunkRows === 25, 'chunk() delivers the 25 rows');

$lazy = 0;
foreach ($sellers()->orderBy('id')->lazy() as $ignored) {
    $lazy++;
}
relational_advanced_assert($lazy === 25, 'lazy() generates 25 elements');

$page = $sellers()->orderBy('id')->paginate(10, 2);
relational_advanced_assert($page->total() === 25, 'paginate() knows the total');
relational_advanced_assert($page->currentPage() === 2 && $page->lastPage() === 3, 'paginate() computes pages');
relational_advanced_assert(count((array) $page->items()) === 10, 'paginate() delivers 10 items');
relational_advanced_assert($page->hasMorePages(), 'paginate() knows whether there are more pages');

$raw = $sellers()->where('name', 'seller5')->rawRecordset();
relational_advanced_assert($raw->count() === 1 && $raw->first()['name'] === 'seller5', 'rawRecordset() does not hydrate');

echo "relational/advanced OK\n";
exit(0);
