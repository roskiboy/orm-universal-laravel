<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql\Driver;

use MongoDB\Client;
use OrmUniversal\Contracts\Exceptions\DriverException;
use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\NoSql\Aggregation\Pipeline;
use OrmUniversal\NoSql\Document;

/**
 * MongoDB driver. Requires ext-mongodb and mongodb/mongodb; when they are not
 * available it fails with an explicit installation message instead of faking
 * support.
 *
 * @api public
 */
final class MongoDbDriver implements DocumentDriverInterface
{
    private ?object $client = null;

    private readonly string $databaseName;

    public function __construct(
        private readonly string $uri = 'mongodb://127.0.0.1:27017',
        string $database = 'ude',
        private readonly array $options = [],
    ) {
        $this->databaseName = $database;
    }

    public static function available(): bool
    {
        return extension_loaded('mongodb') && class_exists(Client::class);
    }

    public function name(): string
    {
        return 'mongodb';
    }

    public function capabilities(): CapabilitySet
    {
        $caps = CapabilitySet::of([
            Capability::NOSQL_JSON_DOCUMENTS,
            Capability::NOSQL_AGGREGATION,
            Capability::NOSQL_PIPELINE,
            Capability::NOSQL_TRANSACTIONS,
            Capability::NOSQL_CHANGE_STREAMS,
            Capability::NOSQL_TTL,
            Capability::NOSQL_PARTIAL_INDEXES,
            Capability::NOSQL_GEO,
            Capability::NOSQL_TEXT_SEARCH,
            Capability::NOSQL_TIME_SERIES,
        ]);

        // Atlas-only vector search is not claimed for self-managed servers.
        return $caps;
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities()->supports($capability);
    }

    public function insertMany(string $collection, array $documents): array
    {
        $result = $this->collection($collection)->insertMany($documents);
        $ids = [];
        foreach ($result->getInsertedIds() as $id) {
            $ids[] = is_object($id) && method_exists($id, '__toString') ? (string) $id : (int) $id;
        }

        return $ids;
    }

    public function find(string $collection, array $filter = [], array $options = []): array
    {
        $query = $this->collection($collection)->find($filter);

        if (isset($options['sort'])) {
            $query = $query->sort($options['sort']);
        }
        if (isset($options['skip'])) {
            $query = $query->skip((int) $options['skip']);
        }
        if (isset($options['limit'])) {
            $query = $query->limit((int) $options['limit']);
        }

        $documents = [];
        foreach ($query as $document) {
            $documents[] = Document::fromArray((array) $document);
        }

        return $documents;
    }

    public function findOne(string $collection, array $filter = []): ?array
    {
        $document = $this->collection($collection)->findOne($filter);

        return $document === null ? null : (array) $document;
    }

    public function updateMany(string $collection, array $filter, array $update): int
    {
        $update = array_intersect_key($update, ['$set' => true, '$inc' => true, '$unset' => true]) === []
            ? ['$set' => $update]
            : $update;

        return $this->collection($collection)->updateMany($filter, $update)->getModifiedCount();
    }

    public function deleteMany(string $collection, array $filter): int
    {
        return $this->collection($collection)->deleteMany($filter)->getDeletedCount();
    }

    public function count(string $collection, array $filter = []): int
    {
        return $this->collection($collection)->countDocuments($filter);
    }

    public function aggregate(string $collection, Pipeline $pipeline): array
    {
        $results = $this->collection($collection)->aggregate($pipeline->stages())->toArray();

        return array_map(static fn (object $document): array => (array) $document, $results);
    }

    public function drop(string $collection): void
    {
        $this->collection($collection)->drop();
    }

    public function collections(): array
    {
        $names = [];
        foreach ($this->database()->listCollections() as $info) {
            $names[] = (string) $info->getName();
        }

        return $names;
    }

    private function client(): object
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (! self::available()) {
            throw new DriverException(
                'The MongoDB driver requires ext-mongodb and mongodb/mongodb. Install the extension and run: composer require mongodb/mongodb',
            );
        }

        $class = Client::class;

        return $this->client = new $class($this->uri, $this->options);
    }

    private function database(): object
    {
        return $this->client()->selectDatabase($this->databaseName);
    }

    private function collection(string $collection): object
    {
        return $this->database()->selectCollection($collection);
    }
}
