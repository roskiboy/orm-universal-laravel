<?php

declare(strict_types=1);

namespace OrmUniversal\Relational\Dialect\Capabilities;

use OrmUniversal\Contracts\Support\Capability;
use OrmUniversal\Contracts\ValueObjects\CapabilitySet;
use OrmUniversal\Contracts\ValueObjects\DialectVersion;

/**
 * Oracle capability matrix for 19c and 23ai/26ai, derived from
 * docs.oracle.com database SQL reference.
 *
 * Documented highlights:
 * - 12c+: identity columns, OFFSET/FETCH, LATERAL, JSON functions, JSON_TABLE (12.2).
 * - 19c: long term release; function based indexes, virtual columns, sequences,
 *   FOR UPDATE SKIP LOCKED, MERGE, LISTAGG (no DISTINCT), DDL is not transactional.
 * - 23ai/26ai: native JSON relational duality, VECTOR type + vector indexes,
 *   BOOLEAN type in SQL, enhanced MERGE.
 *
 * RETURNING is a native Oracle feature but the portable driver path cannot bind
 * OUT parameters yet, so the platform reports it unsupported with the follow-up
 * SELECT alternative instead of pretending.
 *
 * @api public
 */
final class OracleCapabilities
{
    public static function for(DialectVersion $version): CapabilitySet
    {
        $caps = CapabilitySet::of([
            Capability::MERGE,
            Capability::UPSERT,
            Capability::UPSERT_MULTI,
            Capability::GENERATED_COLUMNS,
            Capability::FUNCTIONAL_INDEX,
            Capability::SPATIAL,
            Capability::SEQUENCES,
            Capability::IDENTITY,
            Capability::CHECK_CONSTRAINTS,
            Capability::DEFERRABLE_CONSTRAINTS,
            Capability::ONLINE_INDEX,
            Capability::WINDOW_FUNCTIONS,
            Capability::CTE,
            Capability::RECURSIVE_CTE,
            Capability::MATERIALIZED_VIEW,
            Capability::LATERAL,
            Capability::LOCK,
            Capability::SKIP_LOCKED,
            Capability::NOWAIT,
            Capability::STRING_AGG,
            Capability::PERCENTILE,
            Capability::ROLLUP,
            Capability::GROUPING_SETS,
            Capability::SAVEPOINT,
            Capability::NESTED_TRANSACTIONS,
            Capability::STREAMING_CURSOR,
            Capability::REGEXP,
            Capability::JSON,
            Capability::JSON_TABLE,
            Capability::TIMEZONE,
            Capability::INTERVAL,
            Capability::XML,
            Capability::BINARY_UUID,
            Capability::EXPLAIN,
            Capability::DATE_PART,
        ]);

        if ($version->atLeast(23)) {
            $caps = $caps->with(Capability::VECTOR)
                ->with(Capability::JSON_OPERATORS);
        }

        $identifierLength = $version->atLeast(12, 2) ? 128 : 30;

        return $caps
            ->with(Capability::MAX_PLACEHOLDERS, 65535)
            ->with(Capability::MAX_IDENTIFIER_LENGTH, $identifierLength);
    }
}
