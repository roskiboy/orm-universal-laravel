<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use OrmUniversal\Contracts\QuerySpecificationInterface;

/**
 * Base specification with boolean composition.
 *
 * @api public
 */
abstract class AbstractSpecification implements QuerySpecificationInterface
{
    public function and(QuerySpecificationInterface ...$specifications): CompositeSpecification
    {
        return new CompositeSpecification(CompositeSpecification::AND, [$this, ...$specifications]);
    }

    public function or(QuerySpecificationInterface ...$specifications): CompositeSpecification
    {
        return new CompositeSpecification(CompositeSpecification::OR, [$this, ...$specifications]);
    }

    public function not(): CompositeSpecification
    {
        return new CompositeSpecification(CompositeSpecification::NOT, [$this]);
    }
}
