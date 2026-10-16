<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\Enums\TypeKind;

/**
 * A universal type that knows how to map itself to each dialect.
 *
 * @api public
 */
interface TypeInterface
{
    public function name(): string;

    public function kind(): TypeKind;

    /**
     * Render the database type for the given dialect (e.g. varchar(255), text, jsonb).
     */
    public function databaseType(DialectInterface $dialect): string;

    public function phpType(): string;

    public function cast(mixed $value): mixed;

    public function serialize(mixed $value): mixed;

    public function deserialize(mixed $value): mixed;
}
