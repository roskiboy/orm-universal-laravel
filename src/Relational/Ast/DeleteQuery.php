<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use Closure;
use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\Enums\LogicalOperator;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\ValueObjects\Ordering;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;
use OrmUniversal\Contracts\ValueObjects\TableIdentifier;
use OrmUniversal\Relational\Compiler\CapabilityAnalyzer;

/**
 * DELETE query. A missing WHERE clause is representable but subject to the
 * configurable destructive statement safety policy.
 *
 * @api public
 */
final class DeleteQuery extends Node implements QueryObjectInterface
{
    private ?Expression $where = null;

    private ?int $limit = null;

    /** @var list<Ordering> */
    private array $orders = [];

    /** @var list<string>|null */
    private ?array $returning = null;

    public function __construct(
        private TableIdentifier $table,
    ) {}

    public static function from(string|TableIdentifier $table): self
    {
        return new self(is_string($table) ? TableIdentifier::fromString($table) : $table);
    }

    public function where(Expression|Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
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
            'type' => 'delete',
            'table' => $this->table->qualifiedName(),
            'where' => $this->where?->nodeType(),
            'limit' => $this->limit,
            'returning' => $this->returning,
        ], JSON_THROW_ON_ERROR);

        return QueryFingerprint::fromSql($structure, 0, hash('sha256', $structure));
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
            'where' => $this->where?->nodeType(),
            'has_where' => $this->where !== null,
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
        return $this->where !== null ? [$this->where] : [];
    }

    // --- Accessors --------------------------------------------------------

    public function table(): TableIdentifier
    {
        return $this->table;
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
