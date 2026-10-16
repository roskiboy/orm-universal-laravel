<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\Enums\DatabaseFamily;
use OrmUniversal\Contracts\Enums\DialectVendor;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\CompiledExpression;
use OrmUniversal\Contracts\ValueObjects\CompiledQuery;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * A dialect describes a specific backend + version: capabilities, quoting,
 * compilation and migration DDL. Dialects never claim capabilities they do not
 * verify for the target version.
 *
 * @api public
 */
interface DialectInterface
{
    public function name(): string;

    public function vendor(): DialectVendor;

    public function family(): DatabaseFamily;

    public function version(): DialectVersion;

    public function capabilities(): CapabilitySet;

    public function supports(string $capability): bool;

    /**
     * PDO driver name (mysql, pgsql, sqlite, sqlsrv, oci).
     */
    public function driverName(): string;

    public function quoteIdentifier(string $identifier): string;

    public function compile(QueryObjectInterface $query): CompiledQuery;

    public function compileExpression(ExpressionInterface $expression): CompiledExpression;

    public function migrationCompiler(): MigrationCompilerInterface;

    /**
     * Known limitations for the target version, used by the compatibility report.
     *
     * @return list<string>
     */
    public function limitations(): array;
}
