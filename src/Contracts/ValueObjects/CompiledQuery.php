<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

/**
 * Output of a query compiler: final SQL, ordered bindings, fingerprint, the
 * capabilities that were required while compiling and any translation warnings
 * (hints ignored, features emulated, statements degraded) emitted by the
 * dialect translation layer.
 *
 * @api public
 */
final class CompiledQuery
{
    /**
     * @param  list<mixed>  $bindings
     * @param  list<string>  $requiredCapabilities
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $bindings = [],
        public readonly ?QueryFingerprint $fingerprint = null,
        public readonly array $requiredCapabilities = [],
        public readonly ?string $dialect = null,
        public readonly ?string $dialectVersion = null,
        public readonly array $warnings = [],
    ) {}

    public function fingerprint(): QueryFingerprint
    {
        return $this->fingerprint ?? QueryFingerprint::fromSql($this->sql, count($this->bindings));
    }

    public function bindingCount(): int
    {
        return count($this->bindings);
    }

    /**
     * @param  list<string>  $warnings
     */
    public function withWarnings(array $warnings): self
    {
        if ($warnings === []) {
            return $this;
        }

        return new self(
            $this->sql,
            $this->bindings,
            $this->fingerprint,
            $this->requiredCapabilities,
            $this->dialect,
            $this->dialectVersion,
            array_values(array_unique([...$this->warnings, ...$warnings])),
        );
    }
}
