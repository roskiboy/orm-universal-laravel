<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\Column;
use OrmUniversal\Relational\Ast\InsertQuery;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Dialect contract test against real servers. Each engine is optional: unavailable
 * servers are skipped with an explicit message, never silently passed.
 *
 * @group integration
 */
final class SqlEngineContractTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function engines(): array
    {
        return [
            'mariadb' => ['mysql', (string) (getenv('UDE_MARIADB_HOST') ?: '127.0.0.1'), (string) (getenv('UDE_MARIADB_PORT') ?: '3306'), (string) (getenv('UDE_MARIADB_USERNAME') ?: 'root'), (string) (getenv('UDE_MARIADB_PASSWORD') ?: '')],
            'mysql' => ['mysql', (string) (getenv('UDE_MYSQL_HOST') ?: '127.0.0.1'), (string) (getenv('UDE_MYSQL_PORT') ?: '3307'), (string) (getenv('UDE_MYSQL_USERNAME') ?: 'root'), (string) (getenv('UDE_MYSQL_PASSWORD') ?: '')],
            'pgsql' => ['pgsql', (string) (getenv('UDE_PGSQL_HOST') ?: '127.0.0.1'), (string) (getenv('UDE_PGSQL_PORT') ?: '5432'), (string) (getenv('UDE_PGSQL_USERNAME') ?: 'postgres'), (string) (getenv('UDE_PGSQL_PASSWORD') ?: 'secret')],
        ];
    }

    #[DataProvider('engines')]
    public function test_engine_contract(string $pdoDriver, string $host, string $port, string $user, string $password): void
    {
        $database = $pdoDriver === 'pgsql' ? 'tms_ude_test' : 'tms_ude_test';

        try {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            if ($pdoDriver === 'mysql') {
                $options[PDO::ATTR_EMULATE_PREPARES] = false;
            }

            $pdo = new PDO(
                $pdoDriver.':host='.$host.';port='.$port.';dbname='.$database,
                $user,
                $password,
                $options,
            );
        } catch (PDOException $exception) {
            self::markTestSkipped($pdoDriver.' not reachable: '.$exception->getMessage());
        }

        $dialect = DialectResolver::resolve($pdoDriver, (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
        $executor = new QueryExecutor(new PdoConnection($pdo, 'contract-'.$pdoDriver), $dialect);

        $executor->execute(new RawQuery('drop table if exists ude_contract'));
        $executor->execute(new RawQuery(
            $pdoDriver === 'pgsql'
                ? 'create table ude_contract (id integer primary key, name varchar(100) not null, total numeric(10,2) not null)'
                : 'create table ude_contract (id integer primary key, name varchar(100) not null, total decimal(10,2) not null)',
        ));

        $inserted = InsertQuery::into('ude_contract')
            ->values(['id' => 1, 'name' => 'ana', 'total' => '10.50'])
            ->values(['id' => 2, 'name' => 'bob', 'total' => '20.25']);
        self::assertSame(2, $executor->execute($inserted));

        self::assertSame(2, QueryBuilder::table($executor, 'ude_contract')->count());
        self::assertSame(1, QueryBuilder::table($executor, 'ude_contract')->where(Column::make('total')->gt(15))->count());

        $rows = QueryBuilder::table($executor, 'ude_contract')->orderBy('id')->get();
        self::assertSame('ana', $rows[0]['name']);

        if ($dialect->supports('supportsUpsert')) {
            $upsert = InsertQuery::into('ude_contract')
                ->values(['id' => 1, 'name' => 'ana-updated', 'total' => '11.00'])
                ->onConflict(['id'])
                ->upsert(['name', 'total']);
            $executor->execute($upsert);

            self::assertSame('ana-updated', QueryBuilder::table($executor, 'ude_contract')->where('id', 1)->value('name'));
        }

        $transaction = $executor->transaction();
        $transaction->begin();
        QueryBuilder::table($executor, 'ude_contract')->where('id', 2)->delete();
        self::assertSame(1, QueryBuilder::table($executor, 'ude_contract')->count());
        $transaction->rollback();
        self::assertSame(2, QueryBuilder::table($executor, 'ude_contract')->count());

        if ($dialect->supports('supportsReturning')) {
            $returned = $executor->rows(
                InsertQuery::into('ude_contract')->values(['id' => 3, 'name' => 'carla', 'total' => '5.00'])->returning(['id', 'name']),
            );
            self::assertSame('carla', $returned[0]['name']);
        }

        $executor->execute(new RawQuery('drop table if exists ude_contract'));
    }
}
