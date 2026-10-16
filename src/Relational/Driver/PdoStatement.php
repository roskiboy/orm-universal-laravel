<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Driver;

use OrmUniversal\Contracts\Exceptions\QueryException;
use OrmUniversal\Contracts\StatementInterface;
use PDO;
use PDOException;
use PDOStatement as NativePdoStatement;

/**
 * Thin prepared statement wrapper.
 *
 * @api public
 */
final class PdoStatement implements StatementInterface
{
    private ?NativePdoStatement $statement;

    public function __construct(PDO $pdo, private readonly string $sql)
    {
        try {
            $this->statement = $pdo->prepare($sql);
        } catch (PDOException $exception) {
            throw new QueryException('Unable to prepare statement: '.$exception->getMessage(), 0, $exception, ['sql' => $sql]);
        }
    }

    public function execute(array $bindings = []): bool
    {
        try {
            foreach (array_values($bindings) as $index => $value) {
                $this->statement?->bindValue($index + 1, $value, match (true) {
                    $value === null => PDO::PARAM_NULL,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_int($value) => PDO::PARAM_INT,
                    default => PDO::PARAM_STR,
                });
            }

            return $this->statement?->execute() ?? false;
        } catch (PDOException $exception) {
            throw new QueryException('Statement execution failed: '.$exception->getMessage(), 0, $exception, ['sql' => $this->sql]);
        }
    }

    public function fetch(): ?array
    {
        $row = $this->statement?->fetch();

        return $row === false ? null : $row;
    }

    public function fetchAll(): array
    {
        return $this->statement?->fetchAll() ?: [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement?->fetchColumn($column);
    }

    public function rowCount(): int
    {
        return $this->statement?->rowCount() ?? 0;
    }

    public function close(): void
    {
        $this->statement?->closeCursor();
        $this->statement = null;
    }

    public function sql(): string
    {
        return $this->sql;
    }
}
