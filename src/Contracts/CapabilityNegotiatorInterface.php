<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\Exceptions\UnsupportedFeatureException;

/**
 * Checks required capabilities against a dialect before compilation.
 *
 * @api public
 */
interface CapabilityNegotiatorInterface
{
    /**
     * @return list<string>
     */
    public function requiredFor(AstNodeInterface $node): array;

    /**
     * @throws UnsupportedFeatureException
     */
    public function negotiate(AstNodeInterface $node, DialectInterface $dialect): void;
}
