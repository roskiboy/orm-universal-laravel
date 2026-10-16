<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Compiler\CapabilityAnalyzer;

/**
 * INSERT query with upsert, ignore/replace and RETURNING support negotiated
 * through dialect capabilities.
 *
 * @api public
 */
final class InsertQuery extends Node implements QueryObjectInterface
{
    /** @var list<string> */
    private array $columns = [];

    /** @var list<list<mixed>> */
    private array $rows = [];

    private ?SelectQuery $select = null;

    /** @var list<string>|null */
    private ?array $returning = null;

    private bool $upsert = false;

    /** @var list<string>|null */
    private ?array $updateColumns = null;

    private bool $ignore = false;

    private bool $replace = false;

    /** @var list<string>|null */
    private ?array $conflictColumns = null;

    public function __construct(
        private TableIdentifier $table,
    ) {}

    public static function into(string|TableIdentifier $table): self
    {
        return new self(is_string($table) ? TableIdentifier::fromString($table) : $table);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function values(array $row): self
    {
        $this->columns = array_keys($row);
        $this->rows[] = array_values($row);

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function rows(array $rows): self
    {
        foreach ($rows as $row) {
            $this->values($row);
        }

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function intoColumns(array $columns): self
    {
        $this->columns = array_values($columns);

        return $this;
    }

    public function fromSelect(SelectQuery $select): self
    {
        $this->select = $select;

        return $this;
    }

    public function fromQuery(SelectQuery $select): self
    {
        $this->select = $select;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function returning(array $columns = ['*']): self
    {
        $this->returning = $columns;

        return $this;
    }

    /**
     * @param  list<string>|null  $updateColumns  null updates every non key column
     */
    public function upsert(?array $updateColumns = null): self
    {
        $this->upsert = true;
        $this->updateColumns = $updateColumns;

        return $this;
    }

    public function ignore(): self
    {
        $this->ignore = true;

        return $this;
    }

    public function replace(): self
    {
        $this->replace = true;

        return $this;
    }

    /**
     * Conflict target columns for ON CONFLICT / MERGE upserts.
     *
     * @param  list<string>  $columns
     */
    public function onConflict(array $columns): self
    {
        $this->conflictColumns = $columns;

        return $this;
    }

    public function toAst(): AstNodeInterface
    {
        return $this;
    }

    public function fingerprint(): QueryFingerprint
    {
        $structure = json_encode([
            'type' => 'insert',
            'table' => $this->table->qualifiedName(),
            'columns' => $this->columns,
            'rows' => count($this->rows),
            'select' => $this->select !== null,
            'returning' => $this->returning,
            'upsert' => $this->upsert,
        ], JSON_THROW_ON_ERROR);

        return QueryFingerprint::fromSql($structure, $this->bindingCount(), hash('sha256', $structure));
    }

    /**
     * @return list<string>
     */
    public function requiredCapabilities(): array
    {
        return CapabilityAnalyzer::forNode($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPlan(): array
    {
        return [
            'table' => $this->table->qualifiedName(),
            'columns' => $this->columns,
            'rows' => count($this->rows),
            'from_select' => $this->select !== null,
            'upsert' => $this->upsert,
            'ignore' => $this->ignore,
            'replace' => $this->replace,
            'returning' => $this->returning,
            'required_capabilities' => $this->requiredCapabilities(),
        ];
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        return $this->select !== null ? [$this->select] : [];
    }

    // --- Accessors --------------------------------------------------------

    public function table(): TableIdentifier
    {
        return $this->table;
    }

    /** @return list<string> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<list<mixed>> */
    public function rowsData(): array
    {
        return $this->rows;
    }

    public function selectQuery(): ?SelectQuery
    {
        return $this->select;
    }

    /** @return list<string>|null */
    public function returningColumns(): ?array
    {
        return $this->returning;
    }

    public function isUpsert(): bool
    {
        return $this->upsert;
    }

    /** @return list<string>|null */
    public function updateColumns(): ?array
    {
        return $this->updateColumns;
    }

    public function isIgnore(): bool
    {
        return $this->ignore;
    }

    public function isReplace(): bool
    {
        return $this->replace;
    }

    /** @return list<string>|null */
    public function conflictColumns(): ?array
    {
        return $this->conflictColumns;
    }

    private function bindingCount(): int
    {
        if ($this->select !== null) {
            return count($this->columns);
        }

        return count($this->rows) * count($this->columns);
    }
}
