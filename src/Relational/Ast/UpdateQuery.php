<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use Closure;
use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\JoinType;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\ValueObjects\Ordering;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Compiler\CapabilityAnalyzer;

/**
 * UPDATE query. LIMIT/ORDER BY support is negotiated through dialect capabilities.
 *
 * @api public
 */
final class UpdateQuery extends Node implements QueryObjectInterface
{
    /** @var array<string, mixed> */
    private array $set = [];

    /** @var list<Join> */
    private array $joins = [];

    /** @var list<TableIdentifier> */
    private array $fromTables = [];

    private ?Expression $where = null;

    private ?int $limit = null;

    /** @var list<Ordering> */
    private array $orders = [];

    /** @var list<string>|null */
    private ?array $returning = null;

    public function __construct(
        private TableIdentifier $table,
    ) {}

    public static function make(string|TableIdentifier $table): self
    {
        return new self(is_string($table) ? TableIdentifier::fromString($table) : $table);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): self
    {
        $this->set = array_merge($this->set, $values);

        return $this;
    }

    /**
     * UPDATE ... FROM tables (PostgreSQL). Portability is negotiated by the dialect.
     */
    public function from(string|TableIdentifier ...$tables): self
    {
        foreach ($tables as $table) {
            $this->fromTables[] = is_string($table) ? TableIdentifier::fromString($table) : $table;
        }

        return $this;
    }

    public function join(string|TableIdentifier $table, Expression|Closure|string|null $first = null): self
    {
        if ($first instanceof Closure) {
            $group = new PredicateGroup;
            $first($group);
            $this->joins[] = new Join(JoinType::Inner, is_string($table) ? TableIdentifier::fromString($table) : $table, null, $group->toExpression());

            return $this;
        }

        if ($first instanceof Expression) {
            $this->joins[] = new Join(JoinType::Inner, is_string($table) ? TableIdentifier::fromString($table) : $table, null, $first);

            return $this;
        }

        $this->joins[] = new Join(JoinType::Inner, is_string($table) ? TableIdentifier::fromString($table) : $table);

        return $this;
    }

    public function where(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        $expression = null;

        if ($column instanceof Closure) {
            $group = new PredicateGroup;
            $column($group);
            $expression = $group->toExpression();
        } else {
            $expression = PredicateGroup::predicate($column, $operator, $value);
        }

        if ($expression === null) {
            return $this;
        }

        $this->where = $this->where === null
            ? $expression
            : new LogicalExpression(LogicalOperator::And, [$this->where, $expression]);

        return $this;
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function orderBy(Ordering|string|Expression $column, ?string $direction = null): self
    {
        if ($column instanceof Ordering) {
            $this->orders[] = $column;

            return $this;
        }

        $expression = $column instanceof Expression ? $column : Column::fromPath($column);
        $this->orders[] = strtolower((string) ($direction ?? 'asc')) === 'desc' ? $expression->desc() : $expression->asc();

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

    public function toAst(): AstNodeInterface
    {
        return $this;
    }

    public function fingerprint(): QueryFingerprint
    {
        $structure = json_encode([
            'type' => 'update',
            'table' => $this->table->qualifiedName(),
            'set' => array_keys($this->set),
            'where' => $this->where?->nodeType(),
            'limit' => $this->limit,
            'returning' => $this->returning,
        ], JSON_THROW_ON_ERROR);

        return QueryFingerprint::fromSql($structure, count($this->set), hash('sha256', $structure));
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
            'set' => array_keys($this->set),
            'joins' => count($this->joins),
            'where' => $this->where?->nodeType(),
            'limit' => $this->limit,
            'returning' => $this->returning,
            'required_capabilities' => $this->requiredCapabilities(),
        ];
    }

    /**
     * @return list<AstNodeInterface>
     */
    public function children(): array
    {
        $children = [];
        foreach ($this->joins as $join) {
            $children[] = $join;
        }
        if ($this->where !== null) {
            $children[] = $this->where;
        }

        return $children;
    }

    // --- Accessors --------------------------------------------------------

    public function table(): TableIdentifier
    {
        return $this->table;
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return $this->set;
    }

    /** @return list<Join> */
    public function joins(): array
    {
        return $this->joins;
    }

    /** @return list<TableIdentifier> */
    public function fromTables(): array
    {
        return $this->fromTables;
    }

    public function whereExpression(): ?Expression
    {
        return $this->where;
    }

    public function limitValue(): ?int
    {
        return $this->limit;
    }

    /** @return list<Ordering> */
    public function orders(): array
    {
        return $this->orders;
    }

    /** @return list<string>|null */
    public function returningColumns(): ?array
    {
        return $this->returning;
    }
}
