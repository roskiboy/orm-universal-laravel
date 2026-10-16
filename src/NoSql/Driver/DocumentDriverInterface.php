<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Driver;

use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;

/**
 * Contract for NoSQL backends. Backends keep their native semantics; the
 * capability set declares what is actually implemented and tested.
 *
 * @api public
 */
interface DocumentDriverInterface
{
    public function name(): string;

    public function capabilities(): CapabilitySet;

    public function supports(string $capability): bool;

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<int|string> inserted identifiers
     */
    public function insertMany(string $collection, array $documents): array;

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $options
     * @return list<Document>
     */
    public function find(string $collection, array $filter = [], array $options = []): array;

    /**
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>|null
     */
    public function findOne(string $collection, array $filter = []): ?array;

    /**
     * @param  array<string, mixed>  $filter
     * @param  array<string, mixed>  $update
     */
    public function updateMany(string $collection, array $filter, array $update): int;

    /**
     * @param  array<string, mixed>  $filter
     */
    public function deleteMany(string $collection, array $filter): int;

    /**
     * @param  array<string, mixed>  $filter
     */
    public function count(string $collection, array $filter = []): int;

    public function aggregate(string $collection, Pipeline $pipeline): array;

    public function drop(string $collection): void;

    /**
     * @return list<string>
     */
    public function collections(): array;
}
