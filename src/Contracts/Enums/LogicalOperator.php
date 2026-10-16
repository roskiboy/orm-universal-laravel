<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Boolean connectives.
 *
 * @api public
 */
enum LogicalOperator: string
{
    case And = 'and';
    case Or = 'or';
    case Not = 'not';
    case Xor = 'xor';

    public function sql(): string
    {
        return strtoupper($this->value);
    }
}
