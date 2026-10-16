<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Support;

use Attribute;
use OrmUniversal\Contracts\Enums\ApiStability;

/**
 * Declares lifecycle metadata for a public API: introduced version, stability and
 * deprecated replacement policy.
 *
 * @api public
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final class ApiStatus
{
    public function __construct(
        public readonly ApiStability $stability = ApiStability::Public,
        public readonly string $since = '0.1.0',
        public readonly ?string $deprecatedSince = null,
        public readonly ?string $replacement = null,
        public readonly ?string $removalTarget = null,
    ) {}

    public function isDeprecated(): bool
    {
        return $this->stability === ApiStability::Deprecated || $this->deprecatedSince !== null;
    }
}
