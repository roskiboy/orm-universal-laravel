<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use OrmUniversal\Contracts\Exceptions\PersistenceException;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;

/**
 * Illuminate query builder with an opt-in AST fast path. Whitelisted reads are
 * compiled once through the UDE compiler and executed over the same Laravel
 * connection (same PDO, events, transactions and fetch mode), so Eloquent
 * hydration, model events and query listeners behave exactly as vanilla.
 *
 * Every unsupported shape delegates to the parent implementation.
 *
 * @api public
 */
final class UdeQueryBuilder extends Builder
{
    protected function runSelect()
    {
        $connection = $this->udeConnection();

        if ($connection !== null && ($compiled = $this->udeCompiled($connection)) !== null) {
            return $connection->select(
                $compiled->sql, $compiled->bindings, ! $this->useWritePdo, $this->fetchUsing
            );
        }

        return parent::runSelect();
    }

    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }

        return (new LazyCollection(function () {
            $connection = $this->udeConnection();
            $compiled = $connection !== null ? $this->udeCompiled($connection) : null;

            yield from $this->connection->cursor(
                $compiled !== null ? $compiled->sql : $this->toSql(),
                $compiled !== null ? $compiled->bindings : $this->getBindings(),
                ! $this->useWritePdo,
                $this->fetchUsing,
            );
        }))->map(function ($item) {
            return $this->applyAfterQueryCallbacks(new Collection([$item]))->first();
        })->reject(fn ($item) => is_null($item));
    }

    private function udeConnection(): ?Connection
    {
        return $this->connection instanceof Connection ? $this->connection : null;
    }

    private function udeCompiled(Connection $connection): ?CompiledQuery
    {
        if (! UdeIntercept::enabled($connection)) {
            return null;
        }

        $ast = UdeIntercept::mapper()->map($this);
        if ($ast === null) {
            return null;
        }

        try {
            return UdeIntercept::executor($connection)->compile($ast);
        } catch (PersistenceException) {
            return null;
        }
    }
}
