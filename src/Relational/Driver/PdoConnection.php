<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Driver;

use OrmUniversal\Contracts\ConnectionInterface;
use OrmUniversal\Contracts\CursorInterface;
use OrmUniversal\Contracts\Exceptions\ConnectionException;
use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Contracts\ProfilerInterface;
use OrmUniversal\Contracts\StatementInterface;
use OrmUniversal\Contracts\TransactionInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\QueryProfileEvent;
use OrmUniversal\Relational\Compiler\ParameterPlaceholder;
use OrmUniversal\Relational\Events\QueryExecuted;
use OrmUniversal\Relational\Observability\EventEmitter;
use PDO;
use PDOException;

/**
 * PDO backed connection. Named parameter placeholders are resolved before
 * binding; values never become part of the SQL string.
 *
 * @api public
 */
final class PdoConnection implements ConnectionInterface
{
    /** @var array<string, \PDOStatement> */
    private array $statementCache = [];

    private ?TransactionInterface $transaction = null;

    private bool $closed = false;

    public function __construct(
        private ?PDO $pdo,
        private readonly string $name = 'default',
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?EventEmitter $events = null,
        private readonly int $statementCacheSize = 128,
    ) {
        if ($this->pdo !== null) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function events(): EventEmitter
    {
        return $this->events ?? new EventEmitter;
    }

    public function select(CompiledQuery $query): CursorInterface
    {
        $statement = $this->run($query, true);

        return new PdoCursor($statement, $query, $this->profiler, $this->name, $this->dialectName($query));
    }

    public function execute(CompiledQuery $query): int
    {
        $statement = $this->run($query, false);

        return $statement->rowCount();
    }

    public function selectOne(CompiledQuery $query): ?array
    {
        $statement = $this->run($query, true);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function selectValue(CompiledQuery $query): mixed
    {
        $statement = $this->run($query, true);

        return $statement->fetchColumn();
    }

    /**
     * @param  list<mixed>  $bindings
     */
    public function runCompiled(string $sql, array $bindings = []): int
    {
        return $this->execute(new CompiledQuery($sql, $bindings));
    }

    public function prepare(string $sql): StatementInterface
    {
        return new PdoStatement($this->pdo(), $sql);
    }

    public function transaction(): TransactionInterface
    {
        return $this->transaction ??= new PdoTransaction($this);
    }

    public function lastInsertId(?string $sequence = null): string|int
    {
        try {
            $id = $this->pdo()->lastInsertId($sequence);

            return $id === false ? 0 : $id;
        } catch (PDOException $exception) {
            throw new ConnectionException('Unable to read last insert id on ['.$this->name.'].', 0, $exception);
        }
    }

    public function driverName(): string
    {
        try {
            return (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (PDOException $exception) {
            throw new ConnectionException('Unable to detect the driver on ['.$this->name.'].', 0, $exception);
        }
    }

    public function serverVersion(): string
    {
        try {
            return (string) $this->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (PDOException) {
            return '';
        }
    }

    public function ping(): bool
    {
        if ($this->closed) {
            return false;
        }

        try {
            $this->pdo()->query('select 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function close(): void
    {
        $this->statementCache = [];
        $this->transaction = null;
        $this->pdo = null;
        $this->closed = true;
    }

    public function rawPdo(): ?PDO
    {
        return $this->pdo;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function run(CompiledQuery $query, bool $read): \PDOStatement
    {
        $started = microtime(true);
        $pdo = $this->pdo();
        $bindings = $this->resolveBindings($query->bindings);

        try {
            $statement = $this->cachedStatement($pdo, $query->sql);

            foreach ($bindings as $index => $binding) {
                $statement->bindValue(
                    $index + 1,
                    $binding,
                    match (true) {
                        $binding === null => PDO::PARAM_NULL,
                        is_bool($binding) => PDO::PARAM_BOOL,
                        is_int($binding) => PDO::PARAM_INT,
                        default => PDO::PARAM_STR,
                    },
                );
            }

            $statement->execute();
        } catch (PDOException $exception) {
            throw new QueryException(
                'Query failed on connection ['.$this->name.']: '.$exception->getMessage(),
                0,
                $exception,
                ['sql' => $query->sql, 'driver' => $this->driverName()],
            );
        }

        $durationMs = round((microtime(true) - $started) * 1000, 3);

        if ($this->profiler !== null) {
            $this->profiler->record(new QueryProfileEvent(
                sql: $query->sql,
                fingerprint: $query->fingerprint(),
                durationMs: $durationMs,
                connection: $this->name,
                dialect: $query->dialect,
            ));
        }

        $this->events?->emit(new QueryExecuted(
            sql: $query->sql,
            bindingCount: $query->bindingCount(),
            durationMs: $durationMs,
            connection: $this->name,
            dialect: $query->dialect,
        ));

        return $statement;
    }

    private function cachedStatement(PDO $pdo, string $sql): \PDOStatement
    {
        if ($this->statementCacheSize <= 0) {
            return $pdo->prepare($sql);
        }

        $key = hash('xxh128', $sql);

        if (isset($this->statementCache[$key])) {
            $statement = $this->statementCache[$key];

            unset($this->statementCache[$key]);

            return $this->statementCache[$key] = $statement;
        }

        if (count($this->statementCache) >= $this->statementCacheSize) {
            array_shift($this->statementCache);
        }

        return $this->statementCache[$key] = $pdo->prepare($sql);
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<mixed>
     */
    private function resolveBindings(array $bindings): array
    {
        foreach ($bindings as $index => $binding) {
            if ($binding instanceof ParameterPlaceholder) {
                throw new QueryException(
                    'Unresolved named parameter [:'.$binding->name.'] for connection ['.$this->name.']. Provide the value at execution time.',
                    0,
                    null,
                    ['parameter' => $binding->name],
                );
            }
        }

        return array_values($bindings);
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? throw new ConnectionException('Connection ['.$this->name.'] is closed.');
    }

    private function dialectName(CompiledQuery $query): ?string
    {
        return $query->dialect;
    }
}
