<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\QueryFingerprint;

/**
 * A query that can be inspected and executed. Both Laravel-style and object
 * oriented builders converge here.
 *
 * @api public
 */
interface QueryObjectInterface
{
    public function toAst(): AstNodeInterface;

    public function fingerprint(): QueryFingerprint;

    /**
     * Capabilities required by this query, negotiated against the dialect.
     *
     * @return list<string>
     */
    public function requiredCapabilities(): array;

    /**
     * Debug/inspection snapshot: ast, sql, bindings, dialect, capabilities...
     *
     * @return array<string, mixed>
     */
    public function toPlan(): array;
}
