<?php

declare(strict_types=1);

namespace OrmUniversal\NoSql;

use OrmUniversal\Contracts\Exceptions\DriverException;
use OrmUniversal\NoSql\Driver\DocumentDriverInterface;

/**
 * Document store: registry and factory of NoSQL collections/drivers. Relational
 * and NoSQL engines coexist without contaminating each other's semantics.
 *
 * @api public
 */
final class DocumentStore
{
    /** @var array<string, DocumentDriverInterface> */
    private array $drivers = [];

    private ?string $default = null;

    public function register(string $name, DocumentDriverInterface $driver, bool $default = false): void
    {
        $this->drivers[$name] = $driver;
        $this->default ??= $name;

        if ($default) {
            $this->default = $name;
        }
    }

    public function driver(?string $name = null): DocumentDriverInterface
    {
        $name ??= $this->default ?? throw new DriverException('No NoSQL driver registered.');

        return $this->drivers[$name] ?? throw new DriverException('Unknown NoSQL driver ['.$name.'].');
    }

    public function collection(string $collection, ?string $driver = null): Collection
    {
        return new Collection($this->driver($driver), $collection);
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        return array_keys($this->drivers);
    }

    public function defaultDriver(): ?string
    {
        return $this->default;
    }
}
