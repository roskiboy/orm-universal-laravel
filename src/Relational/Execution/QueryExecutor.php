<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Execution;

use OrmUniversal\Contracts\ConnectionInterface;
use OrmUniversal\Contracts\CursorInterface;
use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Contracts\ProfilerInterface;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\TransactionInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Relational\Ast\RawQuery;
use OrmUniversal\Relational\Ast\SelectQuery;
use OrmUniversal\Relational\Cache\CompiledPlanMemory;
use OrmUniversal\Relational\Cache\QueryCache;
use OrmUniversal\Relational\Compiler\ParameterPlaceholder;
use OrmUniversal\Relational\Dialect\AbstractDialect;

/**
 * Compiles and executes query objects against a connection. Named parameters are
 * resolved here; compilation never sees user values.
 *
 * @api public
 */
final class QueryExecutor
{
    private ?DeferredBatch $deferred = null;

    private bool $reuse = false;

    /** @var array<string, mixed> */
    private array $reusedRows = [];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly AbstractDialect $dialect,
        private readonly ?ProfilerInterface $profiler = null,
        private ?QueryCache $queryCache = null,
    ) {}

    private ?string $appliedStatementTimeout = null;

    /**
     * Apply a per-query statement timeout where the dialect supports it, then
     * restore the previous session value. The default no-op keeps semantics
     * identical on engines without per-query timeouts.
     */
    public function withStatementTimeout(?int $seconds, callable $operation): mixed
    {
        if ($seconds === null || ! $this->dialect->supportsStatementTimeout()) {
            return $operation();
        }

        $statement = $this->dialect->statementTimeoutStatement($seconds);

        if ($statement === null) {
            return $operation();
        }

        $previous = $this->appliedStatementTimeout;

        if ($previous !== $statement) {
            $this->runSessionStatement($statement);
            $this->appliedStatementTimeout = $statement;
        }

        try {
            return $operation();
        } finally {
            if ($previous !== $statement) {
                $restore = $previous ?? $this->dialect->statementTimeoutResetStatement();

                if ($restore !== null) {
                    $this->runSessionStatement($restore);
                    $this->appliedStatementTimeout = $previous;
                }
            }
        }
    }

    private function runSessionStatement(string $sql): void
    {
        $this->connection->execute($this->dialect->compile(new RawQuery($sql)));
    }

    /**
     * Enables the compiled query cache (dialect + version + fingerprint keyed).
     */
    public function withQueryCache(QueryCache $queryCache): self
    {
        $this->queryCache = $queryCache;

        return $this;
    }

    /**
     * Opt-in per-request reuse of duplicate reads (same AST fingerprint and
     * parameters). Documented as a risk: stale reads inside the same request.
     */
    public function withQueryReuse(bool $enabled = true): self
    {
        $this->reuse = $enabled;

        return $this;
    }

    public function deferred(): DeferredBatch
    {
        return $this->deferred ??= new DeferredBatch($this);
    }

    public function flushDeferred(): void
    {
        if ($this->deferred !== null) {
            $this->deferred->flush();
            $this->deferred = null;
        }
    }

    public function flushReuse(): void
    {
        $this->reusedRows = [];
    }

    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function dialect(): AbstractDialect
    {
        return $this->dialect;
    }

    public function compile(QueryObjectInterface $query): CompiledQuery
    {
        if ($this->queryCache === null) {
            return $this->dialect->compile($query);
        }

        $key = $this->queryCache->key($this->dialect, 'compiled', $this->compileFingerprint($query));
        $plan = CompiledPlanMemory::get($key);

        if ($plan instanceof CompiledQuery) {
            return $plan;
        }

        $cached = $this->queryCache->get($key);

        if ($cached instanceof CompiledQuery) {
            CompiledPlanMemory::put($key, $cached);

            return $cached;
        }

        $compiled = $this->dialect->compile($query);
        CompiledPlanMemory::put($key, $compiled);
        $this->queryCache->put($key, $compiled);

        return $compiled;
    }

    /**
     * Drop the process-level plan memo (tooling/tests; long-running workers keep
     * it on purpose: plans are deterministic and bounded).
     */
    public static function flushPlanMemory(): void
    {
        CompiledPlanMemory::flush();
    }

    private function compileFingerprint(QueryObjectInterface $query): string
    {
        try {
            return hash('sha256', serialize($query->toAst()));
        } catch (\Throwable) {
            return (string) $query->fingerprint();
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function rows(QueryObjectInterface $query, array $parameters = []): array
    {
        $key = $this->reuseKey('rows', $query, $parameters);

        if ($key !== null && array_key_exists($key, $this->reusedRows)) {
            /** @var list<array<string, mixed>> */
            return $this->reusedRows[$key];
        }

        $rows = [];

        foreach ($this->cursor($query, $parameters) as $row) {
            $rows[] = $row;
        }

        if ($key !== null) {
            $this->reusedRows[$key] = $rows;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    public function one(QueryObjectInterface $query, array $parameters = []): ?array
    {
        $key = $this->reuseKey('one', $query, $parameters);

        if ($key !== null && array_key_exists($key, $this->reusedRows)) {
            /** @var array<string, mixed>|null */
            return $this->reusedRows[$key];
        }

        $compiled = $this->withParameters($this->compile($query), $parameters);
        $row = $this->connection->selectOne($compiled);

        if ($key !== null) {
            $this->reusedRows[$key] = $row;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function exists(SelectQuery $query, array $parameters = []): bool
    {
        $compiled = $this->withParameters($this->dialect->compileExists($query), $parameters);
        $row = $this->connection->selectOne($compiled);

        return (bool) ($row['exists'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function value(QueryObjectInterface $query, array $parameters = []): mixed
    {
        $compiled = $this->withParameters($this->compile($query), $parameters);

        return $this->connection->selectValue($compiled);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(QueryObjectInterface $query, array $parameters = []): int
    {
        $compiled = $this->withParameters($this->compile($query), $parameters);

        return $this->connection->execute($compiled);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return CursorInterface<array<string, mixed>>
     */
    public function cursor(QueryObjectInterface $query, array $parameters = []): CursorInterface
    {
        $compiled = $this->withParameters($this->compile($query), $parameters);

        return $this->connection->select($compiled);
    }

    public function transaction(): TransactionInterface
    {
        return $this->connection->transaction();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function reuseKey(string $kind, QueryObjectInterface $query, array $parameters): ?string
    {
        if (! $this->reuse) {
            return null;
        }

        try {
            $encoded = serialize($parameters);
        } catch (\Throwable) {
            return null;
        }

        return $kind.':'.(string) $query->fingerprint().':'.hash('xxh128', $encoded);
    }

    /**
     * Replace named parameter placeholders with runtime values.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function withParameters(CompiledQuery $query, array $parameters): CompiledQuery
    {
        if ($query->bindings === []) {
            return $query;
        }

        $bindings = [];
        $changed = false;

        foreach ($query->bindings as $binding) {
            if ($binding instanceof ParameterPlaceholder) {
                if (! array_key_exists($binding->name, $parameters)) {
                    throw new QueryException(
                        'Missing value for named parameter [:'.$binding->name.'].',
                    );
                }

                $bindings[] = $parameters[$binding->name];
                $changed = true;

                continue;
            }

            $bindings[] = $binding;
        }

        if (! $changed) {
            return $query;
        }

        return new CompiledQuery(
            sql: $query->sql,
            bindings: $bindings,
            fingerprint: $query->fingerprint,
            requiredCapabilities: $query->requiredCapabilities,
            dialect: $query->dialect,
            dialectVersion: $query->dialectVersion,
        );
    }
}
