<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Point in time snapshot of a database schema, cacheable and diffable.
 *
 * @api public
 */
final class SchemaSnapshot
{
    /**
     * @param  array<string, TableDefinition>  $tables  keyed by table name
     */
    public function __construct(
        public readonly array $tables = [],
        public readonly ?string $dialect = null,
        public readonly ?string $serverVersion = null,
        public readonly ?string $generatedAt = null,
    ) {}

    public function table(string $name): ?TableDefinition
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array
    {
        return array_keys($this->tables);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dialect' => $this->dialect,
            'server_version' => $this->serverVersion,
            'generated_at' => $this->generatedAt,
            'tables' => array_map(static fn (TableDefinition $table): array => $table->toArray(), $this->tables),
        ];
    }
}
