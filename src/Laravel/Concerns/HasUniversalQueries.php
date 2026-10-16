<?php

declare(strict_types=1);

namespace OrmUniversal\Laravel\Concerns;

use Illuminate\Database\Eloquent\Model;
use OrmUniversal\Contracts\RecordsetInterface;
use OrmUniversal\Laravel\Facades\OrmUniversal;
use OrmUniversal\Relational\Builder\QueryBuilder;

/**
 * Optional trait for Eloquent models: exposes the UDE query builder without
 * changing any Eloquent behavior (hybrid mode).
 *
 * @api public
 */
trait HasUniversalQueries
{
    /**
     * Start a UDE query builder over this model's table/connection.
     */
    public static function universal(): QueryBuilder
    {
        return OrmUniversal::table(static::universalTable(), static::universalConnection());
    }

    public static function universalTable(): string
    {
        /** @var Model $model */
        $model = new static;

        return $model->getTable();
    }

    public static function universalConnection(): string
    {
        /** @var Model $model */
        $model = new static;

        return $model->getConnectionName() ?? (string) config('database.default');
    }

    /**
     * Model instance helper: recordset of the whole table.
     */
    public function universalRecordset(): RecordsetInterface
    {
        return static::universal()->recordset();
    }
}
