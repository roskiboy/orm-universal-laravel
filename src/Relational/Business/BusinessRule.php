<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Business;

use OrmUniversal\Contracts\Exceptions\BusinessOperationException;

/**
 * Business rule: an invariant checked against a subject. Rules never execute SQL.
 *
 * @api public
 */
final class BusinessRule
{
    /**
     * @param  callable(mixed): bool  $predicate
     */
    public function __construct(
        private readonly string $name,
        private $predicate,
        private readonly string $message = '',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function passes(mixed $subject): bool
    {
        return (bool) ($this->predicate)($subject);
    }

    public function assert(mixed $subject): void
    {
        if ($this->passes($subject)) {
            return;
        }

        throw new BusinessOperationException(
            $this->message !== '' ? $this->message : 'Business rule ['.$this->name.'] violated.',
            0,
            null,
            ['rule' => $this->name],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        return ['name' => $this->name, 'message' => $this->message];
    }
}
