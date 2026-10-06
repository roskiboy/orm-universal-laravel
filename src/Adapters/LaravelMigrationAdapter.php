<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Adapters;

use OrmUniversal\Contracts\ConnectionInterface;
use OrmUniversal\Contracts\Exceptions\MigrationException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\TableDefinition;
use OrmUniversal\Relational\Dialect\AbstractDialect;
use OrmUniversal\Relational\Migration\SchemaBuilder;
use OrmUniversal\Relational\Migration\SchemaOperation;

/**
 * Migration adapter: DDL preview, safety review and execution through the core
 * migration engine. Destructive statements are flagged before touching data.
 *
 * @api public
 */
final class LaravelMigrationAdapter
{
    public function __construct(
        private readonly AbstractDialect $dialect,
    ) {}

    /**
     * @return list<CompiledQuery>
     */
    public function preview(TableDefinition $table, bool $ifNotExists = false): array
    {
        return $this->builder()->create($table, $ifNotExists);
    }

    /**
     * @return list<CompiledQuery>
     */
    public function previewOperation(SchemaOperation $operation): array
    {
        return $this->builder()->compile($operation);
    }

    /**
     * Dialect reported lock/blocking warnings for a schema operation.
     *
     * @return list<string>
     */
    public function warningsFor(SchemaOperation $operation): array
    {
        return $this->dialect->migrationWarnings($operation);
    }

    /**
     * @return array{destructive: bool, warnings: list<string>}
     */
    public function safetyReport(string $sql): array
    {
        $normalized = strtolower(trim($sql));
        $warnings = [];
        $destructive = false;

        if (str_starts_with($normalized, 'drop table') || str_starts_with($normalized, 'truncate')) {
            $destructive = true;
            $warnings[] = 'Statement removes table data.';
        }

        if (str_contains($normalized, 'drop column')) {
            $destructive = true;
            $warnings[] = 'Statement removes a column and its data.';
        }

        if (str_contains($normalized, 'drop constraint') || str_contains($normalized, 'drop foreign key')) {
            $warnings[] = 'Statement removes a constraint.';
        }

        if (! str_contains($normalized, 'where') && preg_match('/^(update|delete)\b/', $normalized) === 1) {
            $warnings[] = 'Data statement without WHERE clause.';
        }

        return ['destructive' => $destructive, 'warnings' => $warnings];
    }

    /**
     * @param  list<CompiledQuery>  $statements
     */
    public function assertSafe(array $statements, bool $force = false): void
    {
        foreach ($statements as $statement) {
            $report = $this->safetyReport($statement->sql);

            if ($report['destructive'] && ! $force) {
                throw new MigrationException(
                    'Destructive DDL requires explicit force. Warnings: '.implode(' ', $report['warnings']).' SQL: '.$statement->sql,
                );
            }
        }
    }

    /**
     * @param  list<CompiledQuery>  $statements
     * @return int statements executed
     */
    public function execute(ConnectionInterface $connection, array $statements): int
    {
        $executed = 0;

        foreach ($statements as $statement) {
            $connection->execute($statement);
            $executed++;
        }

        return $executed;
    }

    /**
     * Executes inside a transaction only when the dialect supports transactional
     * DDL; otherwise runs statement by statement and documents the limitation.
     *
     * @param  list<CompiledQuery>  $statements
     */
    public function executeSafely(ConnectionInterface $connection, array $statements): int
    {
        if (! $this->dialect->supports(Capability::DDL_TRANSACTIONAL)) {
            return $this->execute($connection, $statements);
        }

        $transaction = $connection->transaction();
        $transaction->begin();

        try {
            $executed = $this->execute($connection, $statements);
            $transaction->commit();

            return $executed;
        } catch (\Throwable $exception) {
            if ($transaction->isActive()) {
                $transaction->rollback();
            }

            throw $exception;
        }
    }

    private function builder(): SchemaBuilder
    {
        return new SchemaBuilder($this->dialect);
    }
}
