<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use InvalidArgumentException;
use OrmUniversal\Contracts\Exceptions\BusinessOperationException;
use OrmUniversal\Contracts\QuerySpecificationInterface;

/**
 * Composable specification group: AND / OR / NOT.
 *
 * @api public
 */
final class CompositeSpecification implements QuerySpecificationInterface
{
    public const AND = 'and';

    public const OR = 'or';

    public const NOT = 'not';

    /**
     * @param  list<QuerySpecificationInterface>  $specifications
     */
    public function __construct(
        private readonly string $operator,
        private readonly array $specifications,
    ) {
        if (! in_array($operator, [self::AND, self::OR, self::NOT], true)) {
            throw new InvalidArgumentException('Unknown composite operator ['.$operator.'].');
        }
    }

    public function apply(object $query): void
    {
        if ($this->operator === self::NOT) {
            throw new BusinessOperationException(
                'NOT is not expressible as a portable query specification; express the positive condition instead.',
            );
        }

        if (! method_exists($query, 'where')) {
            throw new BusinessOperationException('Composite specifications require a builder supporting where().');
        }

        foreach ($this->specifications as $index => $specification) {
            if ($this->operator === self::OR && $index > 0) {
                $query->orWhere(function ($group) use ($specification): void {
                    $specification->apply($group);
                });

                continue;
            }

            $specification->apply($query);
        }
    }

    /**
     * @return list<QuerySpecificationInterface>
     */
    public function specifications(): array
    {
        return $this->specifications;
    }

    public function operator(): string
    {
        return $this->operator;
    }
}
