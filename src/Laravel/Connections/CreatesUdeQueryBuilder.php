<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Connections;

/**
 * Installs the intercepting query builder on a concrete connection class.
 *
 * @internal
 */
trait CreatesUdeQueryBuilder
{
    public function query()
    {
        return new UdeQueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }
}
