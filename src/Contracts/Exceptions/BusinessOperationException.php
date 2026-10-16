<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when a business operation cannot be resolved or fails its invariants.
 *
 * @api public
 */
class BusinessOperationException extends PersistenceException
{
    public static function ambiguousMethod(string $method, string $model, array $candidates): self
    {
        return new self(
            sprintf(
                'Ambiguous business method [%s] on [%s]. Candidates: %s. Register an unambiguous definition.',
                $method,
                $model,
                implode(', ', $candidates),
            ),
            0,
            null,
            ['method' => $method, 'model' => $model, 'candidates' => $candidates],
        );
    }

    public static function unknownMethod(string $method, string $model): self
    {
        return new self(
            sprintf('Unknown business method [%s] on [%s].', $method, $model),
            0,
            null,
            ['method' => $method, 'model' => $model],
        );
    }
}
