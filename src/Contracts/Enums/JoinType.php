<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * SQL join types. Capability negotiation decides which ones a dialect supports.
 *
 * @api public
 */
enum JoinType: string
{
    case Inner = 'inner';
    case Left = 'left';
    case Right = 'right';
    case Cross = 'cross';
    case Full = 'full';
    case LeftLateral = 'left_lateral';
    case InnerLateral = 'inner_lateral';
}
