<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Execution;

use OrmUniversal\Contracts\Exceptions\QueryException;
use Psr\Log\LoggerInterface;

/**
 * Destructive statement safety policy: UPDATE/DELETE without WHERE can be
 * allowed, warned, require explicit confirmation or denied depending on the
 * environment.
 *
 * @api public
 */
final class DestructiveStatementPolicy
{
    public const ALLOW = 'allow';

    public const WARN = 'warn';

    public const REQUIRE_CONFIRMATION = 'require_confirmation';

    public const DENY = 'deny';

    public function __construct(
        private readonly string $mode = self::WARN,
        private readonly ?LoggerInterface $logger = null,
    ) {
        if (! in_array($mode, [self::ALLOW, self::WARN, self::REQUIRE_CONFIRMATION, self::DENY], true)) {
            throw new \InvalidArgumentException('Unknown destructive statement policy ['.$mode.'].');
        }
    }

    public static function fromString(string $mode, ?LoggerInterface $logger = null): self
    {
        return new self($mode, $logger);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @throws QueryException when the policy denies the statement
     */
    public function assertAllowed(string $statement, bool $hasWhere, bool $confirmed = false): void
    {
        if ($hasWhere || $this->mode === self::ALLOW) {
            return;
        }

        if ($this->mode === self::WARN) {
            $this->logger?->warning('UDE destructive statement without WHERE: '.$statement);

            return;
        }

        if ($this->mode === self::REQUIRE_CONFIRMATION && $confirmed) {
            return;
        }

        throw QueryException::destructiveStatement($statement);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return ['mode' => $this->mode];
    }
}
