<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Public API stability classification.
 *
 * @api public
 */
enum ApiStability: string
{
    case Public = 'public';
    case Experimental = 'experimental';
    case Internal = 'internal';
    case Deprecated = 'deprecated';
}
