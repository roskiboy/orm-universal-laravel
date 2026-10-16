<?php

declare(strict_types=1);

namespace OrmUniversal\Contracts;

use OrmUniversal\Contracts\ValueObjects\TableIdentifier;

/**
 * First class relation definition. Relations are never degraded to bare strings.
 *
 * @api public
 */
interface RelationDefinitionInterface
{
    public function name(): string;

    /**
     * Relation type (hasOne, hasMany, belongsTo, belongsToMany, morphMany, custom...).
     */
    public function type(): string;

    public function relatedTable(): TableIdentifier;

    public function foreignKey(): string;

    public function localKey(): string;

    public function isToMany(): bool;

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
