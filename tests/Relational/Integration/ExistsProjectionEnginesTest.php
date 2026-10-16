<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Tests\Integration;

use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Builder\QueryBuilder;
use OrmUniversal\Relational\Dialect\DialectResolver;
use OrmUniversal\Relational\Driver\PdoConnection;
use OrmUniversal\Relational\Execution\QueryExecutor;
use OrmUniversal\Relational\Observability\InMemoryProfiler;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * EXISTS projection against real servers. Unavailable engines are skipped with an
 * explicit message.
 */
#[Group('integration')]
final class ExistsProjectionEnginesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function engines(): array
    {
        return [
            'mariadb' => ['mysql', (string) (getenv('UDE_MARIADB_HOST') ?: '127.0.0.1'), (string) (getenv('UDE_MARIADB_PORT') ?: '3306'), (string) (getenv('UDE_MARIADB_USERNAME') ?: 'root'), (string) (getenv('UDE_MARIADB_PASSWORD') ?: '')],
            'pgsql' => ['pgsql', (string) (getenv('UDE_PGSQL_HOST') ?: '127.0.0.1'), (string) (getenv('UDE_PGSQL_PORT') ?: '5432'), (string) (getenv('UDE_PGSQL_USERNAME') ?: 'postgres'), (string) (getenv('UDE_PGSQL_PASSWORD') ?: 'secret')],
        ];
    }

    #[DataProvider('engines')]
    public function test_exists_projection_on_real_engine(string $pdoDriver, string $host, string $port, string $user, string $password): void
    {
        try {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            if ($pdoDriver === 'mysql') {
                $options[PDO::ATTR_EMULATE_PREPARES] = false;
            }

            $pdo = new PDO(
                $pdoDriver.':host='.$host.';port='.$port.';dbname=tms_ude_test',
                $user,
                $password,
                $options,
            );
        } catch (PDOException $exception) {
            self::markTestSkipped($pdoDriver.' not reachable: '.$exception->getMessage());
        }

        $dialect = DialectResolver::resolve($pdoDriver, (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

        if (! $dialect->supports('supportsExistsProjection')) {
            self::markTestSkipped($dialect->name().' does not declare EXISTS projections.');
        }

        $profiler = new InMemoryProfiler;
        $executor = new QueryExecutor(new PdoConnection($pdo, 'exists-'.$pdoDriver, $profiler), $dialect);

        $executor->execute(new RawQuery('drop table if exists ude_exists_probe'));
        $executor->execute(new RawQuery('create table ude_exists_probe (id integer primary key, kind varchar(20) not null)'));
        $executor->execute(new RawQuery("insert into ude_exists_probe (id, kind) values (1,'a'),(2,'b')"));

        self::assertTrue(QueryBuilder::table($executor, 'ude_exists_probe')->where('kind', 'a')->exists());
        self::assertFalse(QueryBuilder::table($executor, 'ude_exists_probe')->where('kind', 'z')->exists());

        $events = $profiler->events();
        $direct = end($events)->sql;

        self::assertStringStartsWith('select exists(', $direct);
        self::assertStringNotContainsString('ude_exists_check', $direct);

        $executor->execute(new RawQuery('drop table if exists ude_exists_probe'));
    }
}
