<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Laravel\Pagination\LaravelPagination;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\SqliteDialect;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PHPUnit\Framework\TestCase;

/**
 * Proves that zero hydration returns the same data as Eloquent without creating
 * models, including Eloquent-compatible date serialization and pagination.
 */
final class ZeroHydrationParityTest extends TestCase
{
    private Capsule $capsule;

    private QueryExecutor $executor;

    protected function setUp(): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $pdo = $this->capsule->getConnection()->getPdo();
        $pdo->exec('create table parity_orders (id integer primary key, customer varchar(100) not null, total text not null, status varchar(20) not null, created_at datetime not null)');

        $pdo->beginTransaction();
        $insert = $pdo->prepare('insert into parity_orders (id, customer, total, status, created_at) values (?, ?, ?, ?, ?)');
        for ($i = 1; $i <= 250; $i++) {
            $insert->execute([
                $i,
                'customer'.($i % 25),
                number_format(10 + ($i % 900) / 10, 2, '.', ''),
                $i % 3 === 0 ? 'paid' : 'pending',
                '2026-01-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT).' 12:30:00',
            ]);
        }
        $pdo->commit();

        $this->executor = new QueryExecutor(
            new PdoConnection($pdo, 'parity-test'),
            SqliteDialect::make('3.49.2'),
        );
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();
        Container::setInstance(null);
    }

    public function test_zero_hydration_matches_eloquent_values_and_dates(): void
    {
        $eloquent = ParityOrder::query()->orderBy('id')->limit(100)->get()->toArray();
        $ude = QueryBuilder::table($this->executor, 'parity_orders')
            ->orderBy('id')
            ->limit(100)
            ->castWith([
                'total' => 'decimal:2',
                'created_at' => 'eloquent_json',
            ])
            ->zeroHydration()
            ->get();

        self::assertCount(100, $ude);
        self::assertIsArray($ude[0]);
        self::assertNotInstanceOf(Model::class, $ude[0]);

        foreach ($eloquent as $index => $expected) {
            self::assertSame($expected['id'], $ude[$index]['id']);
            self::assertSame($expected['customer'], $ude[$index]['customer']);
            self::assertSame($expected['total'], $ude[$index]['total']);
            self::assertSame($expected['status'], $ude[$index]['status']);
            self::assertSame($expected['created_at'], $ude[$index]['created_at']);
        }
    }

    public function test_light_hydration_returns_objects_with_same_values(): void
    {
        $rows = QueryBuilder::table($this->executor, 'parity_orders')
            ->orderBy('id')
            ->limit(10)
            ->light()
            ->get();

        self::assertCount(10, $rows);
        self::assertIsObject($rows[0]);
        self::assertSame(1, $rows[0]->id);
        self::assertSame('customer1', $rows[0]->customer);
    }

    public function test_pagination_matches_eloquent_page_and_total(): void
    {
        $ude = LaravelPagination::paginate(
            QueryBuilder::table($this->executor, 'parity_orders')->orderBy('id')->zeroHydration(),
            25,
            2,
            '/parity',
            [],
        );

        $eloquent = ParityOrder::query()->orderBy('id')->paginate(25, ['*'], 'page', 2);

        self::assertSame($eloquent->total(), $ude->total());
        self::assertSame($eloquent->perPage(), $ude->perPage());
        self::assertSame($eloquent->currentPage(), $ude->currentPage());
        self::assertSame($eloquent->lastPage(), $ude->lastPage());
        self::assertSame(
            array_column($eloquent->items(), 'id'),
            array_column($ude->items(), 'id'),
        );
        self::assertIsArray($ude->items()[0]);
    }

    public function test_ude_count_matches_eloquent_count(): void
    {
        $builder = QueryBuilder::table($this->executor, 'parity_orders')->where('status', 'paid');

        self::assertSame(ParityOrder::query()->where('status', 'paid')->count(), $builder->count());
    }
}

final class ParityOrder extends Model
{
    protected $table = 'parity_orders';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'total' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
