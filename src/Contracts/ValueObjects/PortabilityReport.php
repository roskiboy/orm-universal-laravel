<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\ValueObjects;

use OrmUniversal\Contracts\Enums\PortabilityClass;

/**
 * Portability analysis of a query: per-node classification plus human differences.
 *
 * @api public
 */
final class PortabilityReport
{
    /**
     * @param  array<string, PortabilityClass>  $nodes
     * @param  list<string>  $differences
     */
    public function __construct(
        public readonly array $nodes = [],
        public readonly array $differences = [],
    ) {}

    public function classification(): PortabilityClass
    {
        if ($this->nodes === []) {
            return PortabilityClass::Portable;
        }

        $worst = PortabilityClass::Portable;
        foreach ($this->nodes as $node) {
            if ($node === PortabilityClass::DialectSpecific) {
                return PortabilityClass::DialectSpecific;
            }
            if ($node === PortabilityClass::PartiallyPortable) {
                $worst = PortabilityClass::PartiallyPortable;
            }
        }

        return $worst;
    }

    public function percent(): float
    {
        if ($this->nodes === []) {
            return 100.0;
        }

        $sum = 0.0;
        foreach ($this->nodes as $node) {
            $sum += $node->weight();
        }

        return round(($sum / count($this->nodes)) * 100, 2);
    }

    public function isPortable(): bool
    {
        return $this->classification() === PortabilityClass::Portable;
    }

    /**
     * @return array{classification: string, percent: float, differences: list<string>}
     */
    public function toArray(): array
    {
        return [
            'classification' => $this->classification()->value,
            'percent' => $this->percent(),
            'differences' => $this->differences,
        ];
    }
}
