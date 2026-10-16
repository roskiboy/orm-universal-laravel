<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts\Enums;

/**
 * Index structures modelled by the schema engine.
 *
 * @api public
 */
enum IndexType: string
{
    case BTree = 'btree';
    case Hash = 'hash';
    case FullText = 'fulltext';
    case Spatial = 'spatial';
    case Gin = 'gin';
    case Gist = 'gist';
    case Brin = 'brin';
    case Bitmap = 'bitmap';
    case Hnsw = 'hnsw';
    case Ivf = 'ivf';
    case Inverted = 'inverted';
}
