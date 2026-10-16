<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for every error raised by the platform.
 *
 * @api public
 */
class PersistenceException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        protected readonly array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Merge extra contextual data into a copy of this exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        return new static($this->getMessage(), $this->getCode(), $this, array_merge($this->context, $context));
    }
}
