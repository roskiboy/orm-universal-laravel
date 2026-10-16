<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Explicitness level of a raw SQL fragment. Raw SQL is always allowed, but it must
 * declare what it is so security audits can reason about it.
 *
 * @api public
 */
enum RawType: string
{
    /** Trusted developer raw SQL with no external input. */
    case Trusted = 'trusted';
    /** Raw SQL carrying its own explicit bindings. */
    case Parameterized = 'parameterized';
    /** A validated identifier (table/column) supplied by the developer. */
    case Identifier = 'identifier';
    /** A value that will always travel as a binding. */
    case Value = 'value';
}
