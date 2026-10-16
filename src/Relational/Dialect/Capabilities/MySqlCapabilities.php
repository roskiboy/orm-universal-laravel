<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect\Capabilities;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * MySQL capability matrix, derived from dev.mysql.com/doc per version line.
 *
 * Documented cutoffs:
 * - 8.0: CTE, window functions, SKIP LOCKED, CHECK enforced (8.0.16), expression
 *   default values, functional indexes (8.0.13), JSON_TABLE, GROUPING SETS,
 *   REGEXP_LIKE; no RETURNING.
 * - 8.4 LTS: same feature set as 8.0 plus deprecations.
 * - 9.x: VECTOR type (9.0), continuing innovation features.
 *
 * MySQL never supports RETURNING; it uses LAST_INSERT_ID() instead.
 *
 * @api public
 */
final class MySqlCapabilities
{
    public static function for(DialectVersion $version): CapabilitySet
    {
        $caps = CapabilitySet::of([
            Capability::UPSERT,
            Capability::UPSERT_MULTI,
            Capability::GENERATED_COLUMNS,
            Capability::GENERATED_STORED,
            Capability::SPATIAL,
            Capability::FULLTEXT,
            Capability::ENUMS,
            Capability::IDENTITY,
            Capability::UPDATE_LIMIT,
            Capability::DELETE_LIMIT,
            Capability::MULTI_TABLE_UPDATE,
            Capability::INSERT_IGNORE,
            Capability::INSERT_REPLACE,
            Capability::IS_DISTINCT_FROM,
            Capability::REGEXP,
            Capability::SAVEPOINT,
            Capability::NESTED_TRANSACTIONS,
            Capability::STREAMING_CURSOR,
            Capability::UNBUFFERED_QUERY,
            Capability::GROUP_CONCAT,
            Capability::GROUP_CONCAT_DISTINCT,
            Capability::ROLLUP,
            Capability::INTERVAL,
            Capability::SQL_CALC_FOUND_ROWS,
            Capability::EXPLAIN,
            Capability::ONLINE_INDEX,
            Capability::XML,
            Capability::ANY_VALUE,
            Capability::DATE_PART,
            Capability::EXISTS_PROJECTION,
        ]);

        if ($version->atLeast(8, 0)) {
            $caps = $caps->with(Capability::CTE)
                ->with(Capability::RECURSIVE_CTE)
                ->with(Capability::WINDOW_FUNCTIONS)
                ->with(Capability::JSON)
                ->with(Capability::JSON_OPERATORS)
                ->with(Capability::JSON_TABLE)
                ->with(Capability::SKIP_LOCKED)
                ->with(Capability::GROUPING_SETS)
                ->with(Capability::LOCK);
        }

        if ($version->atLeast(8, 0, 13)) {
            $caps = $caps->with(Capability::FUNCTIONAL_INDEX);
        }

        if ($version->atLeast(8, 0, 16)) {
            $caps = $caps->with(Capability::CHECK_CONSTRAINTS);
        }

        if ($version->atLeast(8, 0, 14)) {
            $caps = $caps->with(Capability::LATERAL);
        }

        if ($version->atLeast(9, 0)) {
            $caps = $caps->with(Capability::VECTOR);
        }

        return $caps
            ->with(Capability::MAX_PLACEHOLDERS, 65535)
            ->with(Capability::MAX_IDENTIFIER_LENGTH, 64);
    }
}
