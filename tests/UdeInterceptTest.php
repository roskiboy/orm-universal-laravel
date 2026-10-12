<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use OrmUniversal\Laravel\Connections\UdeConnectionFactory;
use OrmUniversal\Laravel\Connections\UdeIntercept;
use OrmUniversal\Relational\Ast\SelectQuery;
use PHPUnit\Framework\TestCase;

final class UdeInterceptTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        UdeIntercept::flush();

        $container = new Container;
        $container['config'] = new Repository([
            'database' => [
                'default' => 'ude',
                'connections' => [
                    'ude' => ['driver' => 'ude-sqlite', 'database' => ':memory:', 'prefix' => ''],
                    'vanilla' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
                ],
            ],
            'orm-universal' => [
                'auto' => ['mode' => 'intercept', 'intercept' => null],
            ],
        ]);
        Container::setInstance($container);

        $this->db = new DatabaseManager($container, new ConnectionFactory($container));
        UdeConnectionFactory::register($this->db);

        $events = new Dispatcher($container);
        $container['events'] = $events;
        Model::setEventDispatcher($events);
        Model::setConnectionResolver($this->db);

        $this->seed($this->db->connection('ude'));
        $this->seed($this->db->connection('vanilla'));
    }

    protected function tearDown(): void
    {
        Model::unsetEventDispatcher();
        Model::unsetConnectionResolver();
        UdeIntercept::flush();
        Container::setInstance(new Container);
    }

    private function seed(Connection $connection): void
    {
        $connection->statement(
            'create table probe (id integer primary key, name text not null, qty integer null, price numeric not null, active integer not null, deleted_at text null)'
        );

        $connection->insert(
            'insert into probe (id, name, qty, price, active, deleted_at) values (1, ?, ?, ?, ?, ?), (2, ?, ?, ?, ?, ?), (3, ?, ?, ?, ?, ?), (4, ?, ?, ?, ?, ?), (5, ?, ?, ?, ?, ?)',
            [
                'ana', 3, '10.50', 1, null,
                'bob', 0, '3.25', 0, '2026-01-01 00:00:00',
                'carla', 7, '99.99', 1, null,
                'ana', 5, '0.10', 0, null,
                'dave', null, '42.00', 1, null,
            ]
        );

        $connection->statement('create table alt (id integer primary key, probe_id integer not null, label text not null)');
        $connection->insert(
            'insert into alt (id, probe_id, label) values (1, 1, ?), (2, 3, ?), (3, 3, ?)',
            ['x', 'y', 'z']
        );
    }

    public function test_whitelisted_reads_match_vanilla_row_by_row(): void
    {
        foreach ($this->queries() as $name => $callback) {
            $ude = $callback($this->db->connection('ude'));
            $vanilla = $callback($this->db->connection('vanilla'));

            self::assertSame(
                json_encode($vanilla, JSON_THROW_ON_ERROR),
                json_encode($ude, JSON_THROW_ON_ERROR),
                "Query [{$name}] diverged from vanilla Eloquent/Query Builder."
            );
        }
    }

    /**
     * @return array<string, callable(Connection): mixed>
     */
    private function queries(): array
    {
        return [
            'select all ordered' => fn (Connection $c) => $c->table('probe')->orderBy('id')->get()->all(),
            'basic where' => fn (Connection $c) => $c->table('probe')->where('active', 1)->orderBy('id')->get()->all(),
            'two bindings' => fn (Connection $c) => $c->table('probe')->where('active', 1)->where('qty', '>', 2)->orderBy('id')->get()->all(),
            'or where' => fn (Connection $c) => $c->table('probe')->where('name', 'ana')->orWhere('qty', '>', 4)->orderBy('id')->get()->all(),
            'like' => fn (Connection $c) => $c->table('probe')->where('name', 'like', 'a%')->orderBy('id')->get()->all(),
            'where in' => fn (Connection $c) => $c->table('probe')->whereIn('name', ['ana', 'carla'])->orderBy('id')->get()->all(),
            'where not in' => fn (Connection $c) => $c->table('probe')->whereNotIn('name', ['ana'])->orderBy('id')->get()->all(),
            'where null' => fn (Connection $c) => $c->table('probe')->whereNull('qty')->orderBy('id')->get()->all(),
            'where not null' => fn (Connection $c) => $c->table('probe')->whereNotNull('deleted_at')->orderBy('id')->get()->all(),
            'between' => fn (Connection $c) => $c->table('probe')->whereBetween('qty', [3, 5])->orderBy('id')->get()->all(),
            'where column' => fn (Connection $c) => $c->table('probe')->whereColumn('id', '<', 'qty')->orderBy('id')->get()->all(),
            'nested where' => fn (Connection $c) => $c->table('probe')->where(function ($q): void {
                $q->where('name', 'ana')->orWhere('name', 'carla');
            })->where('active', 1)->orderBy('id')->get()->all(),
            'inner join' => fn (Connection $c) => $c->table('probe')
                ->join('alt', 'alt.probe_id', '=', 'probe.id')
                ->select('probe.id', 'probe.name', 'alt.label')
                ->orderBy('probe.id')->orderBy('alt.id')->get()->all(),
            'left join' => fn (Connection $c) => $c->table('probe')
                ->leftJoin('alt', 'alt.probe_id', '=', 'probe.id')
                ->select('probe.id', 'alt.label')
                ->where('probe.id', 2)->orderBy('probe.id')->get()->all(),
            'join alias' => fn (Connection $c) => $c->table('probe as p')
                ->join('alt as a', 'a.probe_id', '=', 'p.id')
                ->select('p.name', 'a.label')
                ->orderBy('p.id')->orderBy('a.id')->get()->all(),
            'multi condition join' => fn (Connection $c) => $c->table('probe')
                ->join('alt', function ($join): void {
                    $join->on('alt.probe_id', '=', 'probe.id')
                        ->on('alt.id', '>=', 'probe.id');
                })
                ->select('probe.id', 'alt.label')
                ->orderBy('probe.id')->orderBy('alt.id')->get()->all(),
            'group by having' => fn (Connection $c) => $c->table('probe')
                ->select('name', 'active')
                ->groupBy('name', 'active')
                ->having('name', '!=', 'bob')
                ->orderBy('name')->orderBy('active')->get()->all(),
            'distinct' => fn (Connection $c) => $c->table('probe')->distinct()->select('name')->orderBy('name')->get()->all(),
            'count' => fn (Connection $c) => $c->table('probe')->where('active', 1)->count(),
            'count all' => fn (Connection $c) => $c->table('probe')->count(),
            'sum' => fn (Connection $c) => $c->table('probe')->sum('qty'),
            'avg' => fn (Connection $c) => $c->table('probe')->avg('qty'),
            'min' => fn (Connection $c) => $c->table('probe')->min('qty'),
            'max' => fn (Connection $c) => $c->table('probe')->max('qty'),
            'exists' => fn (Connection $c) => [$c->table('probe')->where('name', 'nobody')->exists(), $c->table('probe')->where('name', 'ana')->exists()],
            'first' => fn (Connection $c) => $c->table('probe')->orderBy('id')->first(),
            'value' => fn (Connection $c) => $c->table('probe')->orderBy('id')->value('name'),
            'pluck' => fn (Connection $c) => $c->table('probe')->orderBy('id')->pluck('name')->all(),
            'limit offset' => fn (Connection $c) => $c->table('probe')->orderBy('id')->limit(2)->offset(1)->get()->all(),
            'cursor' => fn (Connection $c) => iterator_to_array($c->table('probe')->orderBy('id')->cursor()),
            'chunk by id' => function (Connection $c): array {
                $ids = [];
                $c->table('probe')->orderBy('id')->chunkById(2, function ($rows) use (&$ids): void {
                    foreach ($rows as $row) {
                        $ids[] = $row->id;
                    }
                });

                return $ids;
            },
        ];
    }

    public function test_unsupported_shapes_fall_back_to_laravel_with_same_results(): void
    {
        $queries = [
            'select raw' => fn (Connection $c) => $c->table('probe')->selectRaw('count(*) as total')->first(),
            'where raw' => fn (Connection $c) => $c->table('probe')->whereRaw('qty > ?', [2])->orderBy('id')->get()->all(),
            'order by raw' => fn (Connection $c) => $c->table('probe')->orderByRaw('id desc')->get()->all(),
            'union' => fn (Connection $c) => $c->table('probe')->where('id', 1)
                ->union($c->table('probe')->where('id', 2))->get()->all(),
            'where exists' => fn (Connection $c) => $c->table('probe')->whereExists(function ($q): void {
                $q->selectRaw('1')->from('alt')->whereColumn('alt.probe_id', 'probe.id');
            })->orderBy('id')->get()->all(),
            'group by count' => fn (Connection $c) => $c->table('probe')->select('name')->selectRaw('count(*) as total')
                ->groupBy('name')->having('name', '!=', 'bob')->orderBy('name')->get()->all(),
        ];

        foreach ($queries as $name => $callback) {
            $ude = $callback($this->db->connection('ude'));
            $vanilla = $callback($this->db->connection('vanilla'));

            self::assertSame(
                json_encode($vanilla, JSON_THROW_ON_ERROR),
                json_encode($ude, JSON_THROW_ON_ERROR),
                "Fallback [{$name}] diverged from vanilla."
            );
        }
    }

    public function test_mapper_rejects_unsupported_shapes(): void
    {
        $builder = $this->db->connection('ude')->table('probe')->where('active', 1)->orderBy('id')->limit(2);
        self::assertInstanceOf(SelectQuery::class, UdeIntercept::mapper()->map($builder));

        $raw = $this->db->connection('ude')->table('probe')->whereRaw('1 = 1');
        self::assertNull(UdeIntercept::mapper()->map($raw));

        $prefixed = $this->db->connection('ude')->table('probe')->select('probe.*');
        self::assertInstanceOf(SelectQuery::class, UdeIntercept::mapper()->map($prefixed));
    }

    public function test_fast_path_is_used_and_kill_switch_restores_vanilla(): void
    {
        $ude = $this->db->connection('ude');
        self::assertTrue(UdeIntercept::enabled($ude));

        $captured = [];
        $ude->listen(function ($event) use (&$captured): void {
            $captured[] = $event->sql;
        });

        $ude->table('probe')->join('alt', 'alt.probe_id', '=', 'probe.id')->select('probe.id', 'alt.label')->orderBy('probe.id')->get();
        self::assertStringContainsString('join "alt"', $captured[0]);
        self::assertStringNotContainsString('inner join', $captured[0]);

        $this->config()->set('orm-universal.auto.intercept', false);
        self::assertFalse(UdeIntercept::enabled($ude));

        $captured = [];
        $ude->table('probe')->join('alt', 'alt.probe_id', '=', 'probe.id')->select('probe.id', 'alt.label')->orderBy('probe.id')->get();
        self::assertStringContainsString('inner join "alt"', $captured[0]);
    }

    public function test_activation_flag_is_strict_and_driver_scoped(): void
    {
        $ude = $this->db->connection('ude');
        $vanilla = $this->db->connection('vanilla');
        $config = $this->config();

        $config->set('orm-universal.auto.mode', 'monitor');
        $config->set('orm-universal.auto.intercept', true);
        self::assertTrue(UdeIntercept::enabled($ude));

        $config->set('orm-universal.auto.intercept', 'true');
        self::assertTrue(UdeIntercept::enabled($ude));

        $config->set('orm-universal.auto.intercept', 'false');
        self::assertFalse(UdeIntercept::enabled($ude));

        $config->set('orm-universal.auto.intercept', null);
        self::assertFalse(UdeIntercept::enabled($ude));

        $config->set('orm-universal.auto.mode', 'intercept');
        self::assertTrue(UdeIntercept::enabled($ude));

        $config->set('orm-universal.auto.intercept', false);
        self::assertFalse(UdeIntercept::enabled($ude));

        $config->set('orm-universal.auto.intercept', true);
        self::assertFalse(UdeIntercept::enabled($vanilla));
    }

    public function test_laravel_events_and_model_events_are_preserved(): void
    {
        $ude = $this->db->connection('ude');

        $queries = 0;
        $ude->listen(function () use (&$queries): void {
            $queries++;
        });

        $retrieved = 0;
        InterceptedProbe::retrieved(function () use (&$retrieved): void {
            $retrieved++;
        });

        $models = InterceptedProbe::query()->where('active', 1)->orderBy('id')->get();

        self::assertSame(1, $queries);
        self::assertSame(3, $retrieved);
        self::assertSame('ana', $models[0]->name);
        self::assertSame('carla', $models[1]->name);
        self::assertSame('dave', $models[2]->name);
        self::assertSame(3, $models[0]->qty);
        self::assertSame(10.5, (float) $models[0]->price);
        self::assertSame('ude', $models[0]->getConnectionName());
    }

    private function config(): Repository
    {
        $container = Container::getInstance();
        $config = $container->make('config');
        self::assertInstanceOf(Repository::class, $config);

        return $config;
    }
}

final class InterceptedProbe extends Model
{
    protected $table = 'probe';

    protected $connection = 'ude';

    public $timestamps = false;
}
