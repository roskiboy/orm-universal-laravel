<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Ast;

use OrmUniversal\Contracts\AstNodeInterface;
use OrmUniversal\Contracts\QueryObjectInterface;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;

/**
 * Query object for explicitly compiled SQL with its bindings. Used by count()
 * wrappers, EXISTS checks and trusted internal statements.
 *
 * @api public
 */
final class RawQuery extends Node implements QueryObjectInterface
{
    /**
     * @param  list<mixed>  $bindings
     * @param  list<string>  $capabilities
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $bindings = [],
        private readonly array $capabilities = [],
        private readonly ?string $dialect = null,
        private readonly ?string $dialectVersion = null,
    ) {}

    public function toAst(): AstNodeInterface
    {
        return $this;
    }

    public function compiled(): CompiledQuery
    {
        return new CompiledQuery(
            sql: $this->sql,
            bindings: $this->bindings,
            fingerprint: QueryFingerprint::fromSql($this->sql, count($this->bindings)),
            requiredCapabilities: $this->capabilities,
            dialect: $this->dialect,
            dialectVersion: $this->dialectVersion,
        );
    }

    public function fingerprint(): QueryFingerprint
    {
        return QueryFingerprint::fromSql($this->sql, count($this->bindings));
    }

    /**
     * @return list<string>
     */
    public function requiredCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPlan(): array
    {
        return ['type' => 'raw', 'sql' => $this->sql, 'bindings' => count($this->bindings)];
    }
}
