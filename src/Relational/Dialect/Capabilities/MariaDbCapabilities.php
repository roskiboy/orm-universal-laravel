<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect\Capabilities;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * MariaDB capability matrix, derived from mariadb.com/docs per version line.
 *
 * Documented cutoffs:
 * - 10.0: DELETE/UPDATE ... RETURNING, window functions no
 * - 10.2: CTE + recursive CTE, window functions, JSON functions, CHECK enforced (10.2.1)
 * - 10.3: sequences, LATERAL, system-versioned tables
 * - 10.5: INSERT ... RETURNING
 * - 10.6: SELECT ... SKIP LOCKED (LTS)
 * - 10.7: UUID / INET / ULID types
 * - 11.4: LTS
 * - 11.7+: VECTOR type (MariaDB Vector)
 * - 11.8: LTS, vector GA
 *
 * @api public
 */
final class MariaDbCapabilities
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
            Capability::LATERAL,
            Capability::CTE,
            Capability::RECURSIVE_CTE,
            Capability::WINDOW_FUNCTIONS,
            Capability::JSON,
            Capability::EXISTS_PROJECTION,
        ]);

        if ($version->atLeast(10, 5)) {
            $caps = $caps->with(Capability::RETURNING);
        }

        if ($version->atLeast(10, 2, 7)) {
            $caps = $caps->with(Capability::JSON_OPERATORS);
        }

        if ($version->atLeast(10, 2, 1)) {
            $caps = $caps->with(Capability::CHECK_CONSTRAINTS);
        }

        if ($version->atLeast(10, 3)) {
            $caps = $caps->with(Capability::SEQUENCES, true)
                ->with(Capability::PERCENTILE, true);
        }

        if ($version->atLeast(10, 6)) {
            $caps = $caps->with(Capability::SKIP_LOCKED);
        }

        if ($version->atLeast(10, 4)) {
            $caps = $caps->with(Capability::ROLLUP);
        }

        if ($version->atLeast(10, 7)) {
            $caps = $caps->with(Capability::UUID_NATIVE)
                ->with(Capability::ULID_NATIVE)
                ->with(Capability::BINARY_UUID);
        }

        if ($version->atLeast(11, 7)) {
            $caps = $caps->with(Capability::VECTOR);
        }

        // LOCK capability (SELECT ... FOR UPDATE) exists on InnoDB.
        $caps = $caps->with(Capability::LOCK);

        return $caps
            ->with(Capability::MAX_PLACEHOLDERS, 65535)
            ->with(Capability::MAX_IDENTIFIER_LENGTH, 64)
            ->with(Capability::DATE_PART, true);
    }
}
