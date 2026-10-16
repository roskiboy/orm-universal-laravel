<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Exceptions;

/**
 * Raised when a capability is negotiated and the target backend/version does not
 * support the requested feature. Always reports engine, version, feature, reason
 * and documented alternatives.
 *
 * @api public
 */
final class UnsupportedFeatureException extends DialectException
{
    /**
     * @param  list<string>  $alternatives
     */
    public function __construct(
        public readonly string $feature,
        public readonly string $engine,
        public readonly string $engineVersion,
        public readonly string $reason = 'Capability not supported',
        public readonly array $alternatives = [],
        ?PersistenceException $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Feature [%s] is not supported by %s %s: %s',
                $feature,
                $engine,
                $engineVersion,
                $reason,
            ),
            0,
            $previous,
            [
                'feature' => $feature,
                'engine' => $engine,
                'version' => $engineVersion,
                'reason' => $reason,
                'alternatives' => $alternatives,
            ],
        );
    }

    /**
     * @param  list<string>  $alternatives
     */
    public static function forFeature(
        string $feature,
        string $engine,
        string $engineVersion,
        string $reason = 'Capability not supported',
        array $alternatives = [],
    ): self {
        return new self($feature, $engine, $engineVersion, $reason, $alternatives);
    }

    /**
     * Full human report following the master error contract.
     */
    public function report(): string
    {
        $lines = [
            'Feature: '.$this->feature,
            'Database: '.$this->engine,
            'Server: '.$this->engineVersion,
            'Reason: '.$this->reason,
        ];

        if ($this->alternatives !== []) {
            $lines[] = 'Alternatives:';
            foreach ($this->alternatives as $index => $alternative) {
                $lines[] = '  '.($index + 1).'. '.$alternative;
            }
        }

        return implode(PHP_EOL, $lines);
    }
}
