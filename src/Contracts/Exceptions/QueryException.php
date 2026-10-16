<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when a query is invalid, rejected by a safety policy or fails on the server.
 *
 * @api public
 */
class QueryException extends PersistenceException
{
    public static function destructiveStatement(string $statement): self
    {
        return new self(
            "Destructive statement without WHERE clause blocked: [{$statement}]. Configure the safety policy to allow it explicitly.",
            0,
            null,
            ['statement' => $statement],
        );
    }

    public static function unsafeIdentifier(string $identifier): self
    {
        return new self(
            "Unsafe SQL identifier rejected: [{$identifier}].",
            0,
            null,
            ['identifier' => $identifier],
        );
    }
}
