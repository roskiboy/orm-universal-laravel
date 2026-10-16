<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect\Capabilities;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * PostgreSQL capability matrix for supported majors 14-18, derived from
 * postgresql.org/docs release notes.
 *
 * Documented cutoffs:
 * - 12: stored generated columns
 * - 14/15/16: baseline for arrays, JSONB, GIN/GiST/BRIN, partial + functional
 *   indexes, deferrable constraints, transactional DDL, ILIKE, SKIP LOCKED.
 * - 15: MERGE
 * - 17: JSON_TABLE, incremental backups, MERGE improvements.
 * - 18: virtual generated columns, uuidv7(), temporal constraints,
 *   OLD/NEW in RETURNING, AIO.
 *
 * @api public
 */
final class PostgreSqlCapabilities
{
    public static function for(DialectVersion $version): CapabilitySet
    {
        $caps = CapabilitySet::of([
            Capability::RETURNING,
            Capability::JSON,
            Capability::JSONB,
            Capability::JSON_OPERATORS,
            Capability::WINDOW_FUNCTIONS,
            Capability::CTE,
            Capability::RECURSIVE_CTE,
            Capability::UPSERT,
            Capability::UPSERT_MULTI,
            Capability::INSERT_IGNORE,
            Capability::GENERATED_COLUMNS,
            Capability::GENERATED_STORED,
            Capability::PARTIAL_INDEX,
            Capability::FUNCTIONAL_INDEX,
            Capability::ARRAYS,
            Capability::ENUMS,
            Capability::SEQUENCES,
            Capability::IDENTITY,
            Capability::CHECK_CONSTRAINTS,
            Capability::DEFERRABLE_CONSTRAINTS,
            Capability::DDL_TRANSACTIONAL,
            Capability::ONLINE_INDEX,
            Capability::CONCURRENT_INDEX,
            Capability::ILIKE,
            Capability::REGEXP,
            Capability::SAVEPOINT,
            Capability::NESTED_TRANSACTIONS,
            Capability::STREAMING_CURSOR,
            Capability::LOCK,
            Capability::SKIP_LOCKED,
            Capability::NOWAIT,
            Capability::LATERAL,
            Capability::MATERIALIZED_VIEW,
            Capability::STRING_AGG,
            Capability::STRING_AGG_DISTINCT,
            Capability::PERCENTILE,
            Capability::FILTER_CLAUSE,
            Capability::ROLLUP,
            Capability::GROUPING_SETS,
            Capability::IS_DISTINCT_FROM,

            Capability::VECTOR,
            Capability::TIMEZONE,
            Capability::INTERVAL,
            Capability::XML,
            Capability::UUID_NATIVE,
            Capability::BINARY_UUID,
            Capability::EXPLAIN,
            Capability::EXISTS_PROJECTION,
            Capability::DATE_PART,
        ]);

        if ($version->atLeast(15)) {
            $caps = $caps->with(Capability::MERGE);
        }

        if ($version->atLeast(17)) {
            $caps = $caps->with(Capability::JSON_TABLE);
        }

        return $caps
            ->with(Capability::MAX_PLACEHOLDERS, 65535)
            ->with(Capability::MAX_IDENTIFIER_LENGTH, 63);
    }
}
